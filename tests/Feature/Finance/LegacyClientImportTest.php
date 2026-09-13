<?php

namespace Tests\Feature\Finance;

use App\Models\CashPayment;
use App\Models\Client;
use App\Models\Fund;
use App\Models\User;
use Database\Seeders\FinanceCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class LegacyClientImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FinanceCatalogSeeder::class);
        $this->user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        Fund::withoutGlobalScopes()->whereNull('tenant_id')->update(['tenant_id' => $this->user->tenant_id]);
    }

    public function test_clients_index_has_import_button(): void
    {
        $this->actingAs($this->user)
            ->get(route('cp.clients.index'))
            ->assertOk()
            ->assertSee('استيراد من إكسيل')
            ->assertSee(route('cp.clients.import'), false);
    }

    public function test_import_creates_client_services_and_incoming_payments(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('cp.clients.import.preview'), [
                'files' => [$this->workbookUpload('مخبز السعادة.xlsx')],
            ]);

        $response->assertRedirect();
        $token = $this->tokenFromRedirect($response->headers->get('Location'));
        $this->assertNotEmpty($token);

        $this->actingAs($this->user)
            ->get(route('cp.clients.import.review', ['token' => $token]))
            ->assertOk()
            ->assertSee('مخبز السعادة')
            ->assertSee('إعلان ممول');

        $this->actingAs($this->user)
            ->post(route('cp.clients.import.store'), [
                'token' => $token,
                'clients' => [
                    0 => ['client_name' => 'مخبز السعادة', 'merge' => '0'],
                ],
            ])
            ->assertRedirect(route('cp.clients.index'));

        $client = Client::query()->where('name', 'مخبز السعادة')->first();
        $this->assertNotNull($client);
        $this->assertSame(2, $client->services()->count());
        $this->assertSame(1, $client->payments()->count());

        $currencyId = (int) $client->services()->first()->currency_id;
        $this->assertSame('800.00', $client->billedAmount($currencyId));
        $this->assertSame('500.00', $client->paidAmount($currencyId));
        $this->assertSame('300.00', $client->outstandingAmount($currencyId));
        $this->assertTrue(
            CashPayment::query()->where('party_id', $client->id)->incoming()->active()->exists()
        );
    }

    protected function tokenFromRedirect(?string $location): string
    {
        if (! $location) {
            return '';
        }
        $query = parse_url($location, PHP_URL_QUERY) ?: '';
        parse_str($query, $params);

        return (string) ($params['token'] ?? '');
    }

    protected function workbookUpload(string $name): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('كشف حساب');
        $spreadsheet->getActiveSheet()->fromArray([
            ['نوع الخدمة', 'الشهر'],
            ['إعلان ممول', 'يناير 2025'],
            ['تصميم إعلان', 'فبراير 2025'],
        ]);

        $payments = $spreadsheet->createSheet()->setTitle('دفعات');
        $payments->fromArray([
            ['التاريخ', 'نوع العملة', 'المجموع بالشيكل', 'الملاحظات'],
            ['2025-01-25', 'شيكل', 500, 'جوال باي'],
        ]);

        $totals = $spreadsheet->createSheet()->setTitle('المجموع');
        $totals->fromArray([
            ['الوصف', 'المجموع'],
            ['إعلان ممول', 500],
            ['تصميم إعلان', 300],
            ['المجموع', 800],
        ]);

        $ads = $spreadsheet->createSheet()->setTitle('إعلان ممول');
        $ads->fromArray([
            ['التاريخ', 'المبلغ'],
            ['2025-01-10', 500],
        ]);

        $design = $spreadsheet->createSheet()->setTitle('تصميم إعلان');
        $design->fromArray([
            ['التاريخ', 'المبلغ'],
            ['2025-02-08', 300],
        ]);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'accmoh-feature-'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
