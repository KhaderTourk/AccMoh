<?php

namespace Tests\Unit\Import;

use App\Services\Import\LegacyClientWorkbookParser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class LegacyClientWorkbookParserTest extends TestCase
{
    public function test_parses_statement_payments_totals_and_service_sheets(): void
    {
        $path = $this->writeWorkbook();
        $parsed = app(LegacyClientWorkbookParser::class)->parse($path, 'شركة النور.xlsx');
        @unlink($path);

        $this->assertSame('شركة النور', $parsed['client_name']);
        $this->assertSame([], $parsed['errors']);
        $this->assertCount(3, $parsed['services']);
        $this->assertSame('إعلان ممول', $parsed['services'][0]['title']);
        $this->assertSame('2025-01-05', $parsed['services'][0]['service_date']);
        $this->assertSame('300.00', $parsed['services'][0]['amount']);
        $this->assertSame('2025-02-10', $parsed['services'][1]['service_date']);
        $this->assertSame('300.00', $parsed['services'][1]['amount']);
        $this->assertSame('تصميم إعلان', $parsed['services'][2]['title']);
        $this->assertSame('200.00', $parsed['services'][2]['amount']);

        $this->assertCount(2, $parsed['payments']);
        $this->assertSame('2025-01-20', $parsed['payments'][0]['payment_date']);
        $this->assertSame('400.00', $parsed['payments'][0]['amount']);
        $this->assertSame('ILS', $parsed['payments'][0]['currency_code']);
        $this->assertSame('نقدي', $parsed['payments'][0]['notes']);
        $this->assertSame('2025-03-01', $parsed['payments'][1]['payment_date']);
        $this->assertSame('200.00', $parsed['payments'][1]['amount']);
    }

    public function test_extracts_client_name_and_year_from_statement_filename(): void
    {
        $meta = app(LegacyClientWorkbookParser::class)->parseFilename('2026 - كشف حساب - آكسنت سنتر');

        $this->assertSame('آكسنت سنتر', $meta['name']);
        $this->assertSame(2026, $meta['year']);
    }

    public function test_parses_monthly_ils_template_like_accent_center_file(): void
    {
        $path = $this->writeAccentStyleWorkbook();
        $parsed = app(LegacyClientWorkbookParser::class)->parse($path, '2026 - كشف حساب - آكسنت سنتر.xlsx');
        @unlink($path);

        $this->assertSame('آكسنت سنتر', $parsed['client_name']);
        $this->assertSame([], $parsed['errors']);
        $this->assertCount(2, $parsed['services']);
        $this->assertSame('إعلانات ممولة', $parsed['services'][0]['title']);
        $this->assertSame('2026-05-01', $parsed['services'][0]['service_date']);
        $this->assertSame('319.90', $parsed['services'][0]['amount']);
        $this->assertSame('2026-06-01', $parsed['services'][1]['service_date']);
        $this->assertSame('199.70', $parsed['services'][1]['amount']);

        $this->assertCount(4, $parsed['payments']);
        $this->assertSame('100.00', $parsed['payments'][0]['amount']);
        $this->assertSame('بنك', $parsed['payments'][0]['notes']);
        $this->assertSame('جوال', $parsed['payments'][1]['notes']);
        $this->assertSame([], $parsed['warnings']);
    }

    protected function writeAccentStyleWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $totals = $spreadsheet->getActiveSheet();
        $totals->setTitle('المجموع');
        $totals->fromArray([
            ['المجموع', 'الوصف'],
            [0, 'إدارة صفحة'],
            [519.6, 'إعلانات ممولة'],
            [0, 'أخرى'],
            [400, 'دفعات'],
            [119.6, 'المجموع بالشيكل'],
        ]);

        $ads = $spreadsheet->createSheet();
        $ads->setTitle('إعلانات ممولة');
        $ads->fromArray([
            ['ملاحظات', 'المجموع بالشيكل', 'سعر الدولار', 'دولار', 'الشهر'],
            [null, 0, 5, 0, 1],
            [null, 0, 5, 0, 2],
            [null, 0, 5, 0, 3],
            [null, 0, 5, 0, 4],
            [null, 319.9, 5, 63.98, 5],
            [null, 199.7, 5, 39.94, 6],
            [null, 0, 5, 0, 7],
            [null, 0, 5, 0, 8],
            [null, 0, 5, 0, 9],
            [null, 0, 5, 0, 10],
            [null, 0, 5, 0, 11],
            [null, 0, 5, 0, 12],
            [null, 519.6, 'المجموع'],
        ]);

        $page = $spreadsheet->createSheet();
        $page->setTitle('إدارة صفحة');
        $page->fromArray([
            ['ملاحظات', 'المجموع بالشيكل', 'شيكل', 'سعر الدولار', 'دولار', 'الشهر'],
            [null, 0, null, null, null, 1],
            [null, 0, 'المجموع'],
        ]);

        $payments = $spreadsheet->createSheet();
        $payments->setTitle('دفعات');
        $payments->fromArray([
            ['ملاحظات', 'المجموع بالشيكل', 'شيكل', 'سعر الدولار', 'دولار', 'التاريخ'],
            ['بنك', 100, 100, null, null, '2026-05-28'],
            ['جوال', 100, 100, null, null, '2026-05-20'],
            ['بنك', 100, 100, null, null, '2026-06-02'],
            ['بنك', 100, 100, null, null, '2026-07-20'],
            [null, 0],
            [null, 400, 'المجموع'],
        ]);

        $statement = $spreadsheet->createSheet();
        $statement->setTitle('كشف حساب');
        $statement->fromArray([
            ['دفعات', 'أخرى', 'إعلانات ممولة', 'إدارة صفحة', 'الشهر'],
            ['التفصيل في دفعات', null, 0, 0, 1],
            [null, null, 319.9, 0, 5],
            [null, null, 199.7, 0, 6],
            [400, 0, 519.6, 0, 'المجموع'],
        ]);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'accmoh-accent-'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function test_splits_totals_across_dated_service_rows_without_amounts(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('كشف حساب');
        $spreadsheet->getActiveSheet()->fromArray([
            ['نوع الخدمة', 'الشهر'],
            ['إدارة صفحات', 'مارس 2025'],
        ]);

        $payments = $spreadsheet->createSheet()->setTitle('دفعات');
        $payments->fromArray([
            ['التاريخ', 'نوع العملة', 'المجموع بالشيكل', 'الملاحظات'],
        ]);

        $totals = $spreadsheet->createSheet()->setTitle('المجموع');
        $totals->fromArray([
            ['الوصف', 'المجموع'],
            ['إدارة صفحات', 400],
            ['المجموع', 400],
        ]);

        $service = $spreadsheet->createSheet()->setTitle('إدارة صفحات');
        $service->fromArray([
            ['التاريخ'],
            ['2025-03-01'],
            ['2025-03-15'],
        ]);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'accmoh-import-split-'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $parsed = app(LegacyClientWorkbookParser::class)->parse($path, 'عميل الصفحات.xlsx');
        @unlink($path);

        $this->assertCount(2, $parsed['services']);
        $this->assertSame('200.00', $parsed['services'][0]['amount']);
        $this->assertSame('200.00', $parsed['services'][1]['amount']);
        $this->assertSame('2025-03-01', $parsed['services'][0]['service_date']);
        $this->assertSame('2025-03-15', $parsed['services'][1]['service_date']);
    }

    protected function writeWorkbook(): string
    {
        $spreadsheet = new Spreadsheet;
        $statement = $spreadsheet->getActiveSheet();
        $statement->setTitle('كشف حساب');
        $statement->fromArray([
            ['نوع الخدمة', 'الشهر'],
            ['إعلان ممول', 'يناير 2025'],
            ['تصميم إعلان', 'فبراير 2025'],
        ]);

        $payments = $spreadsheet->createSheet();
        $payments->setTitle('دفعات');
        $payments->fromArray([
            ['التاريخ', 'نوع العملة', 'المجموع بالشيكل', 'الملاحظات'],
            ['2025-01-20', 'شيكل', 400, 'نقدي'],
            ['2025-03-01', 'شيكل', 200, 'بنكي'],
        ]);

        $other = $spreadsheet->createSheet();
        $other->setTitle('أخري');
        $other->fromArray([['تجاهل']]);

        $totals = $spreadsheet->createSheet();
        $totals->setTitle('المجموع');
        $totals->fromArray([
            ['الوصف', 'المجموع'],
            ['إعلان ممول', 600],
            ['تصميم إعلان', 200],
            ['المجموع', 800],
        ]);

        $ads = $spreadsheet->createSheet();
        $ads->setTitle('إعلان ممول');
        $ads->fromArray([
            ['التاريخ', 'المبلغ'],
            ['2025-01-05', 300],
            ['2025-02-10', 300],
        ]);

        $design = $spreadsheet->createSheet();
        $design->setTitle('تصميم إعلان');
        $design->fromArray([
            ['التاريخ', 'المبلغ'],
            ['2025-02-20', 200],
        ]);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'accmoh-import-'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
