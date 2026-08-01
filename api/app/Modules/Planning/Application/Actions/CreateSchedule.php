<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Modules\Planning\Domain\Models\Schedule;

class CreateSchedule
{
    public function handle(array $data): Schedule
    {
        return Schedule::query()->create($data);
    }
}
