<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Queries;

use App\Modules\CRM\Domain\Models\CrmTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Issue #5720 — Liste des tâches CRM (filtres allowlistés, tri fixe).
 *
 * Filtres : `status` (todo|in_progress|done|cancelled), `overdue` (bool),
 * `priority` (low|medium|high), `owner_id`, `account_id`. Aucun tri/SQL libre :
 * ordre fixe `due_at ASC NULLS LAST, id DESC` (les tâches les plus urgentes
 * d'abord). N+1 évité (assignee + account eager-loadés).
 */
class ListCrmTasksQuery
{
    /**
     * @param  array{
     *     status?: string,
     *     overdue?: bool,
     *     priority?: string,
     *     owner_id?: int,
     *     account_id?: int,
     *     per_page?: int,
     *     page?: int,
     * }  $input
     */
    /** @param array{status?: string, assignee_id?: int, per_page?: int, page?: int} $input
     * @return LengthAwarePaginator<int, CrmTask> */
    public function execute(array $input): LengthAwarePaginator
    {
        $query = CrmTask::query()
            ->with('assignee:id,first_name,last_name')
            ->orderByRaw('due_at ASC NULLS LAST')
            ->orderByDesc('id');

        if (isset($input['status'])) {
            $query->where('status', $input['status']);
        }

        if (isset($input['overdue'])) {
            if ($input['overdue']) {
                $query->whereIn('status', ['todo', 'in_progress'])->where('due_at', '<', now());
            } else {
                $query->where(fn ($builder) => $builder->where('due_at', '>=', now())->orWhereNull('due_at'));
            }
        }

        if (isset($input['priority'])) {
            $query->where('priority', $input['priority']);
        }

        if (isset($input['owner_id'])) {
            $query->where('assigned_to', (int) $input['owner_id']);
        }

        if (isset($input['account_id'])) {
            $query->where('account_id', (int) $input['account_id']);
        }

        $perPage = min((int) ($input['per_page'] ?? 25), 50);

        return $query->paginate($perPage, ['*'], 'page', (int) ($input['page'] ?? 1));
    }
}
