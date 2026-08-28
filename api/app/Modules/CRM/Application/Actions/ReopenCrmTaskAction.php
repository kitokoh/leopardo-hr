<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Domain\Models\CrmTask;
use Illuminate\Validation\ValidationException;

/**
 * Issue #5720 — Rouvrir une tâche CRM clôturée (status → todo).
 */
final class ReopenCrmTaskAction
{
    public function execute(CrmTask $task): CrmTask
    {
        if ($task->status === 'todo' || $task->status === 'in_progress') {
            return $task;
        }

        if ($task->status !== 'done') {
            throw ValidationException::withMessages([
                'status' => ['INVALID_TASK_STATUS_TRANSITION'],
            ]);
        }

        $task->status = 'todo';
        $task->save();

        return $task;
    }
}
