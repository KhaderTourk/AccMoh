<?php

namespace App\Services\Import;

use App\Enums\ClientServiceStatus;
use App\Enums\PaymentDirection;
use App\Exceptions\FinanceException;
use App\Models\Client;
use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\ServiceType;
use App\Services\Finance\CashPaymentService;
use App\Services\Finance\ClientWorkService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class LegacyClientImportService
{
    public function __construct(
        protected LegacyClientWorkbookParser $parser,
        protected ClientWorkService $work,
        protected CashPaymentService $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $draft
     * @return array{client: Client, created: bool, services: int, payments: int, skipped_payments: int, warnings: list<string>}
     */
    public function importOne(array $draft): array
    {
        $warnings = $draft['warnings'] ?? [];
        $name = trim((string) ($draft['client_name'] ?? ''));
        if ($name === '') {
            throw new FinanceException('اسم الزبون مطلوب.');
        }

        return DB::transaction(function () use ($draft, $name, $warnings) {
            $existing = Client::query()
                ->where(function ($q) use ($name) {
                    $q->where('name', $name)->orWhere('company_name', $name);
                })
                ->first();
            $merge = (bool) ($draft['merge'] ?? true);
            $created = false;

            if ($existing && $merge) {
                $client = $existing;
                if (filled($draft['phone'] ?? null) && ! $client->phone) {
                    $client->phone = $draft['phone'];
                }
                if (filled($draft['company_name'] ?? null) && ! $client->company_name) {
                    $client->company_name = $draft['company_name'];
                }
                $client->save();
            } else {
                $client = Client::query()->create([
                    'name' => $name,
                    'company_name' => $draft['company_name'] ?? $name,
                    'phone' => $draft['phone'] ?? null,
                    'notes' => $draft['notes'] ?? 'مستورد من ملف إكسيل قديم',
                    'is_active' => true,
                ]);
                $created = true;
            }

            $serviceCount = 0;
            foreach ($draft['services'] ?? [] as $row) {
                if ($this->isDuplicateService($client, $row)) {
                    $warnings[] = "تم تخطي خدمة مكررة: {$row['title']} في {$row['service_date']}.";
                    continue;
                }

                $type = $this->resolveServiceType($row['title']);
                $currency = $this->resolveCurrency($row['currency_code'] ?? 'ILS');
                $payload = [
                    'client_id' => $client->id,
                    'service_type_id' => $type?->id,
                    'title' => $row['title'],
                    'amount' => $row['amount'],
                    'currency_id' => $currency->id,
                    'service_date' => $row['service_date'],
                    'status' => ClientServiceStatus::Completed,
                    'notes' => $row['notes'] ?? 'مستورد من إكسيل',
                ];
                if (! empty($row['source_amount']) && ! empty($row['exchange_rate'])) {
                    $payload['source_amount'] = $row['source_amount'];
                    $payload['exchange_rate'] = $row['exchange_rate'];
                }
                $this->work->create($payload);
                $serviceCount++;
            }

            $paymentCount = 0;
            $skippedPayments = 0;
            foreach ($draft['payments'] ?? [] as $row) {
                $currency = $this->resolveCurrency($row['currency_code'] ?? 'ILS');
                $method = $this->resolvePaymentMethod($row['payment_method_hint'] ?? $row['notes'] ?? null);

                $this->payments->record([
                    'direction' => PaymentDirection::Incoming,
                    'party_type' => 'client',
                    'party_id' => $client->id,
                    'amount' => $row['amount'],
                    'currency_id' => $currency->id,
                    'payment_method_id' => $method->id,
                    'name' => $client->personName(),
                    'occurred_on' => $row['payment_date'],
                    'notes' => $row['notes'] ?? 'مستورد من إكسيل',
                ]);
                $paymentCount++;
            }

            return [
                'client' => $client->fresh(),
                'created' => $created,
                'services' => $serviceCount,
                'payments' => $paymentCount,
                'skipped_payments' => $skippedPayments,
                'warnings' => array_values(array_unique($warnings)),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function isDuplicateService(Client $client, array $row): bool
    {
        return $client->services()
            ->where('title', $row['title'])
            ->whereDate('service_date', $row['service_date'])
            ->where('amount', Money::of($row['amount']))
            ->exists();
    }

    protected function resolveServiceType(string $title): ?ServiceType
    {
        $wanted = $this->parser->serviceTypeKey($title);
        $match = ServiceType::query()->get()->first(
            fn (ServiceType $type) => $this->parser->serviceTypeKey($type->name) === $wanted
        );

        if ($match) {
            return $match;
        }

        $partial = ServiceType::query()->get()->first(function (ServiceType $type) use ($wanted) {
            $have = $this->parser->serviceTypeKey($type->name);

            return $have !== '' && $wanted !== '' && (str_contains($wanted, $have) || str_contains($have, $wanted));
        });

        if ($partial) {
            return $partial;
        }

        return ServiceType::query()->create([
            'name' => $title,
            'is_active' => true,
        ]);
    }

    protected function resolveCurrency(string $code): Currency
    {
        return Currency::query()->where('code', strtoupper($code))->first()
            ?? Currency::query()->where('code', 'ILS')->firstOrFail();
    }

    protected function resolvePaymentMethod(?string $hint): PaymentMethod
    {
        $n = $this->parser->normalize((string) $hint);
        $slug = 'cash';
        if ($this->contains($n, ['جوال باي', 'جوالباي', 'جوال', 'jawwal', 'jawal'])) {
            $slug = 'jawwal_pay';
        } elseif ($this->contains($n, ['بال باي', 'بالباي', 'palpay', 'pal pay', 'pal-pay'])) {
            $slug = 'palpay';
        } elseif ($this->contains($n, ['بنكي', 'بنك', 'تحويل', 'bank'])) {
            $slug = 'bank';
        } elseif ($this->contains($n, ['نقدي', 'كاش', 'cash'])) {
            $slug = 'cash';
        }

        return PaymentMethod::query()->where('slug', $slug)->first()
            ?? PaymentMethod::query()->orderBy('sort_order')->firstOrFail();
    }

    /**
     * @param  list<string>  $needles
     */
    protected function contains(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $this->parser->normalize($needle))) {
                return true;
            }
        }

        return false;
    }
}
