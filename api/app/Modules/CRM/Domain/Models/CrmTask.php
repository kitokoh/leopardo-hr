<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Enums\CrmRelatedType;
use App\Modules\CRM\Domain\Enums\CrmTaskStatus;
use App\Shared\Traits\Auditable;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Tâche CRM bornée — Issue #5710 (CRM-V0-06).
 *
 * Cycle de vie contraint : `todo` → `in_progress` → `done` | `cancelled`.
 * `done` horodate `completed_at` (garde `markAsDone()`) ; une tâche
 * `done`/`cancelled` est terminale via l'API. Le partage est explicite :
 * un propriétaire (`assignee_id`) + des assignés supplémentaires via la
 * table pivot `crm_task_assignees`. Mutations auditées (`Auditable`).
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $subject
 * @property string|null $description
 * @property string $status
 * @property string $priority
 * @property Carbon|null $due_at
 * @property Carbon|null $completed_at
 * @property int|null $assignee_id
 * @property int|null $created_by_id
 * @property string|null $related_type
 * @property int|null $related_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Employee> $assignees
 *
 * @mixin Builder<static>
 */
class CrmTask extends Model
{
    use Auditable;
    use BelongsToCompany;

    protected $table = 'crm_tasks';

    protected $fillable = [
        'company_id',
        'subject',
        'description',
        'status',
        'priority',
        'due_at',
        'completed_at',
        'assignee_id',
        'created_by_id',
        'related_type',
        'related_id',
        'metadata',
    ];

    protected $casts = [
        'assignee_id' => 'integer',
        'created_by_id' => 'integer',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'related_id' => 'integer',
        'metadata' => 'encrypted:array',
    ];

    /**
     * Assignés supplémentaires (partage explicite).
     *
     * @return BelongsToMany<Employee, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'crm_task_assignees', 'task_id', 'employee_id')
            ->withPivot('assigned_by_id')
            ->withTimestamps();
    }

    /**
     * Passe la tâche à `done` en horodatant `completed_at` (idempotent).
     */
    public function markAsDone(): void
    {
        $this->status = CrmTaskStatus::Done->value;
        $this->completed_at = $this->completed_at ?? now();
        $this->save();
    }

    /**
     * Réouvre une tâche `done`/`in_progress` vers `todo` (garde API : jamais
     * depuis `cancelled`).
     */
    public function reopen(): void
    {
        if ($this->status === CrmTaskStatus::Cancelled->value) {
            return;
        }

        $this->status = CrmTaskStatus::Todo->value;
        $this->completed_at = null;
        $this->save();
    }

    /**
     * Filtrer les tâches échues et non terminées.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_at', '<', now())
            ->whereNotIn('status', [CrmTaskStatus::Done->value, CrmTaskStatus::Cancelled->value]);
    }

    /**
     * Filtrer sur une cible (lead, opportunity, contact, account).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForRelated(Builder $query, CrmRelatedType $type, int $relatedId): Builder
    {
        return $query->where('related_type', $type->value)
            ->where('related_id', $relatedId);
    }
}
