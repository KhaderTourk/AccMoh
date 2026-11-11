<?php

namespace Tests\Unit;

use App\Support\StatementFilename;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class StatementFilenameTest extends TestCase
{
    public function test_uses_first_and_last_movement_with_dashes(): void
    {
        $this->assertSame(
            'أحمد علي - كشف حساب - 1-10-2026 لغاية 5-11-2026.pdf',
            StatementFilename::make('أحمد علي', null, null, ['2026-11-05', Carbon::parse('2026-10-01'), '2026-10-20'])
        );
    }

    public function test_period_filter_wins_and_bad_characters_are_dropped(): void
    {
        $this->assertSame(
            'مطبعة النور - كشف حساب - 1-9-2026 لغاية 30-9-2026.pdf',
            StatementFilename::make('مطبعة/النور', '2026-09-01', '2026-09-30', ['2026-01-01'])
        );
        $this->assertSame('سامي - كشف حساب.pdf', StatementFilename::make('سامي', null, null, []));
    }
}
