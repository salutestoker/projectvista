<?php

declare(strict_types=1);

namespace Tests\Unit\ProjectVista;

use App\Services\Scheduling\WorkingDayCalendar;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class WorkingDayCalendarTest extends TestCase
{
    public function test_add_working_days_skips_weekends(): void
    {
        $calendar = new WorkingDayCalendar;

        $date = $calendar->addWorkingDays(CarbonImmutable::parse('2024-04-18'), 30);

        $this->assertSame('2024-05-30', $date->toDateString());
    }

    public function test_next_working_day_moves_weekend_to_monday(): void
    {
        $calendar = new WorkingDayCalendar;

        $date = $calendar->nextWorkingDay(CarbonImmutable::parse('2024-06-15'));

        $this->assertSame('2024-06-17', $date->toDateString());
    }
}
