<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final readonly class DateRange
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    public static function from(CarbonInterface|string $start, CarbonInterface|string $end): self
    {
        return new self(
            CarbonImmutable::parse($start)->startOfDay(),
            CarbonImmutable::parse($end)->startOfDay(),
        );
    }

    public function overlaps(self $range): bool
    {
        return $this->start->lessThanOrEqualTo($range->end)
            && $this->end->greaterThanOrEqualTo($range->start);
    }
}
