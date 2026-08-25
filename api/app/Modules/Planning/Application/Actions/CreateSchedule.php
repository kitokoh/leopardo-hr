<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Modules\Planning\Domain\Models\Schedule;

class CreateSchedule
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): Schedule
    {
        return Schedule::query()->create($data);
    }
}
