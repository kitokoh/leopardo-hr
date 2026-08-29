<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\Auditable;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * #5710 — Tâche CRM client (tenant-scoped).
 *
 * Tâche V0 alignée sur le schéma inline main (CrmPilotSeederTest) et sur les
 * seeders #5743 : titre, description, échéance (`due_at`), ownership
 * (`assignee_id` — employé du tenant, validité contrôlée par les Policies
 * V0, issue #5711) et achèvement binaire (`done`).
 *
 * Les mutations sont auditées automatiquement via `Auditable`.
 *
 * @property int $id
 * @property string $company_id
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $due_at
 * @property int|null $assignee_id
 * @property bool $done
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
        'title',
        'description',
        'due_at',
        'assignee_id',
        'done',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'assignee_id' => 'integer',
        'done' => 'boolean',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_id');
    }

    /**
     * Marque la tâche comme terminée (idempotent).
     */
    public function markAsDone(): void
    {
        $this->done = true;
        $this->save();
    }

    /**
     * Une tâche est en retard si elle a une échéance passée et n'est pas
     * terminée.
     */
    public function isOverdue(?Carbon $now = null): bool
    {
        $now ??= now();

        return ! $this->done
            && $this->due_at !== null
            && $this->due_at->lt($now);
    }
}
