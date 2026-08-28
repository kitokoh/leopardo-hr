<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\Auditable;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #5710 — Entrée de timeline CRM client (tenant-scoped).
 *
 * **Append-only** : la timeline est un journal ; l'API V0 n'expose aucune
 * mutation (pas de PUT/DELETE) sur ces lignes. `occurred_at` porte la date
 * métier (pagination temporelle), `created_at` l'horodatage d'insertion.
 *
 * Les mutations (INSERT) sont auditées automatiquement via le trait
 * `Auditable` (table `audit_logs`, action `created`).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $account_id
 * @property int|null $contact_id
 * @property int|null $lead_id
 * @property int|null $opportunity_id
 * @property string $type
 * @property string|null $subject
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmActivity extends Model
{
    use Auditable;
    use BelongsToCompany;

    public const TYPE_NOTE = 'note';

    public const TYPE_CALL = 'call';

    public const TYPE_EMAIL = 'email';

    public const TYPE_MEETING = 'meeting';

    public const TYPE_TASK_CREATED = 'task_created';

    public const TYPE_TASK_COMPLETED = 'task_completed';

    public const TYPE_STAGE_CHANGED = 'stage_changed';

    public const TYPE_STATUS_CHANGED = 'status_changed';

    public const TYPE_SYSTEM = 'system';

    public const TYPES = [
        self::TYPE_NOTE,
        self::TYPE_CALL,
        self::TYPE_EMAIL,
        self::TYPE_MEETING,
        self::TYPE_TASK_CREATED,
        self::TYPE_TASK_COMPLETED,
        self::TYPE_STAGE_CHANGED,
        self::TYPE_STATUS_CHANGED,
        self::TYPE_SYSTEM,
    ];

    protected $table = 'crm_activities';

    protected $fillable = [
        'company_id',
        'account_id',
        'contact_id',
        'lead_id',
        'opportunity_id',
        'type',
        'subject',
        'description',
        'occurred_at',
        'created_by',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
