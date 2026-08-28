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
 * Tâche CRM client (tenant, issue #5710).
 *
 * Tâche bornée : titre limité (200), statuts/priorités allowlistés (CHECK
 * en base), échéance (`due_at`) et complétion horodatées. L'ownership est
 * portée par `assigned_to` (employé du tenant, validité contrôlée par les
 * Policies V0, issue #5711).
 *
 * Les mutations sont auditées automatiquement via `Auditable`.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $account_id
 * @property int|null $contact_id
 * @property int|null $lead_id
 * @property int|null $opportunity_id
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property string $priority
 * @property Carbon|null $due_at
 * @property Carbon|null $completed_at
 * @property int|null $assigned_to
 * @property int|null $completed_by
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmTask extends Model
{
    use Auditable;
    use BelongsToCompany;

    public const STATUS_TODO = 'todo';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_TODO,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DONE,
        self::STATUS_CANCELLED,
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
    ];

    protected $table = 'crm_tasks';

    protected $fillable = [
        'company_id',
        'account_id',
        'contact_id',
        'lead_id',
        'opportunity_id',
        'title',
        'description',
        'status',
        'priority',
        'due_at',
        'completed_at',
        'assigned_to',
        'completed_by',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function complete(?int $actorId = null): void
    {
        $this->status = self::STATUS_DONE;
        $this->completed_at = now();
        $this->completed_by = $actorId;
    }

    public function isOverdue(?Carbon $now = null): bool
    {
        $now ??= now();

        return $this->status !== self::STATUS_DONE
            && $this->status !== self::STATUS_CANCELLED
            && $this->due_at !== null
            && $this->due_at->lt($now);
    }
}
