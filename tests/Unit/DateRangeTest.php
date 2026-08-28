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

    public function test_label_for_full_range(): void
    {
        $this->assertSame('طوال المدة', DateRange::label(null, null));
        $this->assertSame('من 2026-08-01 إلى 2026-08-28', DateRange::label('2026-08-01', '2026-08-28'));
    }
}
