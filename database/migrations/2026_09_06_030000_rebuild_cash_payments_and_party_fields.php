<?php

use App\Enums\LoanDirection;
use App\Enums\PaymentDirection;
use App\Enums\TransactionType;
use App\Models\CashPayment;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Expense;
use App\Models\FamilyLoan;
use App\Models\FamilyLoanRepayment;
use App\Models\Person;
use App\Models\Vendor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('currencies')->where('code', 'ILS')->update(['name' => 'شيكل']);
        DB::table('currencies')->where('code', 'JOD')->update(['name' => 'دينار أردني']);
        DB::table('payment_methods')->where('slug', 'bank')->update(['name' => 'بنك فلسطين']);

        if (Schema::hasTable('vendors')) {
            Schema::table('vendors', function (Blueprint $table) {
                if (! Schema::hasColumn('vendors', 'job_title')) {
                    $table->string('job_title')->nullable()->after('phone');
                }
                if (! Schema::hasColumn('vendors', 'work_description')) {
                    $table->string('work_description')->nullable()->after('job_title');
                }
            });
        }

        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'contact_name')) {
            $clients = DB::table('clients')->whereNotNull('contact_name')->where('contact_name', '!=', '')->get();
            foreach ($clients as $client) {
                $organization = filled($client->company_name) ? $client->company_name : $client->name;
                DB::table('clients')->where('id', $client->id)->update([
                    'company_name' => $organization,
                    'name' => $client->contact_name,
                ]);
            }
        }

        if (! Schema::hasTable('cash_payments')) {
            Schema::create('cash_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('direction', 20);
                $table->foreignId('fund_id')->constrained()->restrictOnDelete();
                $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
                $table->foreignId('currency_id')->constrained()->restrictOnDelete();
                $table->decimal('amount', 14, 2);
                $table->decimal('source_amount', 14, 2)->nullable();
                $table->decimal('exchange_rate', 18, 8)->nullable();
                $table->foreignId('fx_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
                $table->string('name');
                $table->string('account_holder_name')->nullable();
                $table->nullableMorphs('party');
                $table->date('occurred_on');
                $table->text('notes')->nullable();
                $table->uuid('ledger_group_id');
                $table->boolean('is_reversed')->default(false);
                $table->timestamp('reversed_at')->nullable();
                $table->string('source_type', 80)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'direction', 'occurred_on']);
                $table->index(['source_type', 'source_id']);
            });
        }

        $this->migrateClientPayments();
        $this->migrateExpenses();
        $this->migrateLoans();
        $this->migrateRepayments();
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_payments');
    }

    protected function migrateClientPayments(): void
    {
        if (! Schema::hasTable('client_payments')) {
            return;
        }

        foreach (DB::table('client_payments')->orderBy('id')->get() as $row) {
            $id = $this->insertPayment([
                'tenant_id' => $row->tenant_id ?? null,
                'direction' => PaymentDirection::Incoming->value,
                'fund_id' => $row->fund_id,
                'payment_method_id' => $row->payment_method_id,
                'currency_id' => $row->currency_id,
                'amount' => $row->amount,
                'source_amount' => $row->source_amount ?? null,
                'exchange_rate' => $row->exchange_rate ?? null,
                'fx_currency_id' => $row->fx_currency_id ?? null,
                'name' => $row->payer_name ?: (DB::table('clients')->where('id', $row->client_id)->value('name') ?: 'زبون'),
                'account_holder_name' => $row->payer_name,
                'party_type' => 'client',
                'party_id' => $row->client_id,
                'occurred_on' => $row->payment_date,
                'notes' => $row->notes,
                'ledger_group_id' => $row->ledger_group_id ?: 'migrated-'.$row->id,
                'is_reversed' => (bool) $row->is_reversed,
                'reversed_at' => $row->reversed_at,
                'source_type' => 'client_payment',
                'source_id' => $row->id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            $this->repointLedger(
                ClientPayment::class,
                $row->id,
                $id,
                TransactionType::ClientPayment->value,
                TransactionType::IncomingPayment->value
            );
        }
    }

    protected function migrateExpenses(): void
    {
        if (! Schema::hasTable('expenses')) {
            return;
        }

        foreach (DB::table('expenses')->orderBy('id')->get() as $row) {
            $vendorName = $row->vendor_id
                ? DB::table('vendors')->where('id', $row->vendor_id)->value('name')
                : null;
            $id = $this->insertPayment([
                'tenant_id' => $row->tenant_id ?? null,
                'direction' => PaymentDirection::Outgoing->value,
                'fund_id' => $row->fund_id,
                'payment_method_id' => $row->payment_method_id,
                'currency_id' => $row->currency_id,
                'amount' => $row->amount,
                'source_amount' => null,
                'exchange_rate' => null,
                'fx_currency_id' => null,
                'name' => $row->payee ?: ($vendorName ?: ($row->description ?: 'دفعة صادرة')),
                'account_holder_name' => $row->payee,
                'party_type' => $row->vendor_id ? 'vendor' : null,
                'party_id' => $row->vendor_id,
                'occurred_on' => $row->expense_date,
                'notes' => $row->notes,
                'ledger_group_id' => $row->ledger_group_id ?: 'migrated-exp-'.$row->id,
                'is_reversed' => (bool) $row->is_reversed,
                'reversed_at' => $row->reversed_at,
                'source_type' => 'expense',
                'source_id' => $row->id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            $this->repointLedger(
                Expense::class,
                $row->id,
                $id,
                TransactionType::Expense->value,
                TransactionType::OutgoingPayment->value
            );
        }
    }

    protected function migrateLoans(): void
    {
        if (! Schema::hasTable('family_loans')) {
            return;
        }

        foreach (DB::table('family_loans')->orderBy('id')->get() as $row) {
            $incoming = $row->direction === LoanDirection::Borrowed->value;
            $personName = DB::table('family_members')->where('id', $row->family_member_id)->value('name') ?: 'شخص';
            $id = $this->insertPayment([
                'tenant_id' => $row->tenant_id ?? null,
                'direction' => $incoming ? PaymentDirection::Incoming->value : PaymentDirection::Outgoing->value,
                'fund_id' => $row->fund_id,
                'payment_method_id' => $row->payment_method_id,
                'currency_id' => $row->currency_id,
                'amount' => $row->amount,
                'source_amount' => $row->source_amount ?? null,
                'exchange_rate' => $row->exchange_rate ?? null,
                'fx_currency_id' => $row->fx_currency_id ?? null,
                'name' => $personName,
                'account_holder_name' => null,
                'party_type' => 'person',
                'party_id' => $row->family_member_id,
                'occurred_on' => $row->loan_date,
                'notes' => $row->notes,
                'ledger_group_id' => $row->ledger_group_id ?: 'migrated-loan-'.$row->id,
                'is_reversed' => (bool) $row->is_reversed,
                'reversed_at' => $row->reversed_at,
                'source_type' => 'family_loan',
                'source_id' => $row->id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            $fromType = $incoming
                ? TransactionType::FamilyLoanReceived->value
                : TransactionType::FamilyLoanGiven->value;
            $toType = $incoming
                ? TransactionType::IncomingPayment->value
                : TransactionType::OutgoingPayment->value;

            $this->repointLedger(FamilyLoan::class, $row->id, $id, $fromType, $toType);
        }
    }

    protected function migrateRepayments(): void
    {
        if (! Schema::hasTable('family_loan_repayments')) {
            return;
        }

        foreach (DB::table('family_loan_repayments')->orderBy('id')->get() as $row) {
            // Repaying a borrowed loan = money out. Repaying a lent loan = money in.
            $outgoing = $row->direction === LoanDirection::Borrowed->value;
            $personName = DB::table('family_members')->where('id', $row->family_member_id)->value('name') ?: 'شخص';
            $id = $this->insertPayment([
                'tenant_id' => $row->tenant_id ?? null,
                'direction' => $outgoing ? PaymentDirection::Outgoing->value : PaymentDirection::Incoming->value,
                'fund_id' => $row->fund_id,
                'payment_method_id' => $row->payment_method_id,
                'currency_id' => $row->currency_id,
                'amount' => $row->amount,
                'source_amount' => $row->source_amount ?? null,
                'exchange_rate' => $row->exchange_rate ?? null,
                'fx_currency_id' => $row->fx_currency_id ?? null,
                'name' => $personName,
                'account_holder_name' => null,
                'party_type' => 'person',
                'party_id' => $row->family_member_id,
                'occurred_on' => $row->repayment_date,
                'notes' => $row->notes,
                'ledger_group_id' => $row->ledger_group_id ?: 'migrated-repay-'.$row->id,
                'is_reversed' => (bool) $row->is_reversed,
                'reversed_at' => $row->reversed_at,
                'source_type' => 'family_loan_repayment',
                'source_id' => $row->id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            $fromType = $outgoing
                ? TransactionType::FamilyLoanRepaymentPaid->value
                : TransactionType::FamilyLoanRepaymentReceived->value;
            $toType = $outgoing
                ? TransactionType::OutgoingPayment->value
                : TransactionType::IncomingPayment->value;

            $this->repointLedger(FamilyLoanRepayment::class, $row->id, $id, $fromType, $toType);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function insertPayment(array $payload): int
    {
        $now = now();
        $payload['created_at'] = $payload['created_at'] ?? $now;
        $payload['updated_at'] = $payload['updated_at'] ?? $now;

        return DB::table('cash_payments')->insertGetId($payload);
    }

    protected function repointLedger(string $fromClass, int $fromId, int $toId, string $fromType, string $toType): void
    {
        if (! Schema::hasTable('ledger_entries')) {
            return;
        }

        DB::table('ledger_entries')
            ->where('related_type', $fromClass)
            ->where('related_id', $fromId)
            ->update([
                'related_type' => CashPayment::class,
                'related_id' => $toId,
            ]);

        DB::table('ledger_entries')
            ->where('transaction_type', $fromType)
            ->where('related_type', CashPayment::class)
            ->where('related_id', $toId)
            ->where('is_reversal', false)
            ->update(['transaction_type' => $toType]);
    }
};
