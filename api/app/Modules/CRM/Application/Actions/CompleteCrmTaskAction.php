<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Domain\Models\CrmTask;
use Illuminate\Validation\ValidationException;

/**
 * Issue #5720 — Clôturer une tâche CRM (status → done).
 */
final class CompleteCrmTaskAction
{
    public function execute(CrmTask $task): CrmTask
    {
        if ($task->status === 'done') {
            return $task;
        }

        if ($task->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => ['INVALID_TASK_STATUS_TRANSITION'],
            ]);
        }

        $task->status = 'done';
        $task->save();

        return $task;
    }
}
