<?php

namespace Tests\Unit;

use App\Support\DateRange;
use Illuminate\Http\Request;
use Tests\TestCase;

class DateRangeTest extends TestCase
{
    public function test_swaps_inverted_dates(): void
    {
        $request = Request::create('/', 'GET', ['from' => '2026-08-20', 'to' => '2026-08-01']);
        [$from, $to] = DateRange::fromRequest($request);

        $this->assertSame('2026-08-01', $from);
        $this->assertSame('2026-08-20', $to);
    }

    public function test_empty_strings_become_null(): void
    {
        $request = Request::create('/', 'GET', ['from' => '', 'to' => '']);
        [$from, $to] = DateRange::fromRequest($request);

        $this->assertNull($from);
        $this->assertNull($to);
    }

    public function test_invalid_calendar_dates_become_null(): void
    {
        $request = Request::create('/', 'GET', ['from' => 'not-a-date', 'to' => '2026-13-99']);
        [$from, $to] = DateRange::fromRequest($request);

        $this->assertNull($from);
        $this->assertNull($to);
    }

    public function test_preset_current_month_overrides_inputs(): void
    {
        $request = Request::create('/', 'GET', [
            '_preset' => 'current_month',
            'from' => '2020-01-01',
            'to' => '2020-01-31',
        ]);
        [$from, $to] = DateRange::fromRequest($request);

        $this->assertSame(now()->startOfMonth()->toDateString(), $from);
        $this->assertSame(now()->toDateString(), $to);
    }

    public function test_preset_all_clears_range(): void
    {
        $request = Request::create('/', 'GET', [
            '_preset' => 'all',
            'from' => '2026-08-01',
            'to' => '2026-08-31',
        ]);
        [$from, $to] = DateRange::fromRequest($request);

        $this->assertNull($from);
        $this->assertNull($to);
    }

    public function test_query_params_omit_empty_dates(): void
    {
        $this->assertSame(['q' => 'ahmad'], DateRange::queryParams(null, null, ['q' => 'ahmad']));
        $this->assertSame(
            ['from' => '2026-08-01', 'to' => '2026-08-31'],
            DateRange::queryParams('2026-08-01', '2026-08-31')
        );
    }

    public function test_label_for_full_range(): void
    {
        $this->assertSame('طوال المدة', DateRange::label(null, null));
        $this->assertSame('من 01\\08\\2026 إلى 28\\08\\2026', DateRange::label('2026-08-01', '2026-08-28'));
    }

    public function test_display_uses_day_month_year(): void
    {
        $this->assertSame('15\\09\\2026', DateRange::display('2026-09-15'));
        $this->assertSame('15\\09\\2026 15:30', DateRange::display('2026-09-15 15:30:00', true));
        $this->assertSame('', DateRange::display(null));
    }

    public function test_display_dates_normalize_to_storage(): void
    {
        $this->assertSame('2026-09-15', DateRange::toStorageDate('15\\09\\2026'));
        $this->assertSame('2026-09-15', DateRange::toStorageDate('15/09/2026'));
        $this->assertSame('15\\09\\2026', DateRange::inputValue('2026-09-15'));
        $this->assertSame('2026-09-15', DateRange::normalize('15\\09\\2026'));
        $this->assertNull(DateRange::toStorageDate('32\\13\\2026'));
        $this->assertNull(DateRange::normalize('32\\13\\2026'));
    }

    public function test_storage_date_accepts_arabic_digits_and_common_separators(): void
    {
        $this->assertSame('2026-10-17', DateRange::toStorageDate('١٧\\١٠\\٢٠٢٦'));
        $this->assertSame('2026-10-17', DateRange::toStorageDate('۱۷/۱۰/۲۰۲۶'));
        $this->assertSame('2026-05-01', DateRange::toStorageDate('1-5-2026'));
        $this->assertSame('2026-05-01', DateRange::toStorageDate('1.5.2026'));
        $this->assertNull(DateRange::toStorageDate('2026-05-01'));
    }

    public function test_valid_date_enforces_calendar_and_year_range(): void
    {
        $this->assertSame('2026-10-17', DateRange::toValidDate('2026-10-17'));
        $this->assertSame('2026-10-17', DateRange::toValidDate('17\\10\\2026'));
        $this->assertNull(DateRange::toValidDate('2026-02-29'));
        $this->assertNull(DateRange::toValidDate('0226-10-17'));
        $this->assertNull(DateRange::toValidDate('2026-10-17 10:00:00'));
        $this->assertNull(DateRange::toValidDate(null));
    }

    public function test_input_value_does_not_reinterpret_invalid_text(): void
    {
        $this->assertSame('1710', DateRange::inputValue('1710'));
        $this->assertSame('31\\02\\2026', DateRange::inputValue('31\\02\\2026'));
        $this->assertSame('01\\05\\2026', DateRange::inputValue('1\\5\\2026'));
        $this->assertSame('17\\10\\2026', DateRange::inputValue(now()->setDate(2026, 10, 17)));
        $this->assertSame('', DateRange::inputValue(null));
    }
}
