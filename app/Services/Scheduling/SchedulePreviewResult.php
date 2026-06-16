<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use Illuminate\Support\Collection;

final readonly class SchedulePreviewResult
{
    /**
     * @param  Collection<int, ScheduleConflict>  $conflicts
     */
    public function __construct(public Collection $conflicts) {}

    public function canSave(): bool
    {
        return $this->conflicts->isEmpty();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'can_save' => $this->canSave(),
            'requires_override' => false,
            'conflicts' => $this->conflicts
                ->map(fn (ScheduleConflict $conflict) => $conflict->toArray())
                ->values()
                ->all(),
        ];
    }
}
