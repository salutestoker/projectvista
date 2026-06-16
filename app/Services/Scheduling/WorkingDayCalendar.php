<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class WorkingDayCalendar
{
    public function isWorkingDay(CarbonInterface $date): bool
    {
        return ! $date->isWeekend();
    }

    public function addWorkingDays(CarbonInterface|string $date, int $days): CarbonImmutable
    {
        $current = CarbonImmutable::parse($date)->startOfDay();
        $remaining = $days;

        while ($remaining > 0) {
            $current = $current->addDay();

            if ($this->isWorkingDay($current)) {
                $remaining--;
            }
        }

        return $current;
    }

    public function countWorkingDaysInclusive(CarbonInterface|string $start, CarbonInterface|string $end): int
    {
        $current = CarbonImmutable::parse($start)->startOfDay();
        $last = CarbonImmutable::parse($end)->startOfDay();
        $count = 0;

        if ($last->lessThan($current)) {
            return 1;
        }

        while ($current->lessThanOrEqualTo($last)) {
            if ($this->isWorkingDay($current)) {
                $count++;
            }

            $current = $current->addDay();
        }

        return max(1, $count);
    }

    public function nextWorkingDay(CarbonInterface|string $date): CarbonImmutable
    {
        $current = CarbonImmutable::parse($date)->startOfDay();

        while (! $this->isWorkingDay($current)) {
            $current = $current->addDay();
        }

        return $current;
    }

    public function workingDateRange(CarbonInterface|string $earliestStart, int $durationWorkingDays): DateRange
    {
        $duration = max(1, $durationWorkingDays);
        $start = $this->nextWorkingDay($earliestStart);
        $end = $this->addWorkingDays($start, $duration - 1);

        return new DateRange($start, $end);
    }
}
