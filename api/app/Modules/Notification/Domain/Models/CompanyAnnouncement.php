<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Domain\Models\Department;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PA2-COMM-004 — Company-scoped announcement broadcast by a manager to the
 * whole company, one department, or a single employee.
 *
 * PA2-COMM-011 — Moderation lifecycle: an announcement can be saved as a
 * `draft` (never fanned out), `scheduled` for a future `scheduled_at` (fan
 * out happens via `announcements:publish-scheduled`), immediately
 * `published` (the only behaviour that existed before this ticket, still
 * the default so every pre-existing row and every request that omits
 * `scheduled_at` keeps working exactly as before), or `cancelled` (a
 * draft/scheduled announcement withdrawn before fan-out, kept for audit
 * instead of being deleted).
 *
 * @property int $id
 * @property string $company_id
 * @property int $created_by
 * @property string $title
 * @property string $body
 * @property string $priority
 * @property string $audience_type
 * @property int|null $audience_department_id
 * @property int|null $audience_employee_id
 * @property string $status
 * @property Carbon|null $published_at
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property int $recipients_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CompanyAnnouncement extends Model
{
    use BelongsToCompany;

    protected $table = 'company_announcements';

    protected $fillable = [
        'company_id',
        'created_by',
        'title',
        'body',
        'priority',
        'audience_type',
        'audience_department_id',
        'audience_employee_id',
        'status',
        'published_at',
        'scheduled_at',
        'expires_at',
        'cancelled_at',
        'cancelled_by',
        'recipients_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const AUDIENCE_COMPANY = 'company';

    public const AUDIENCE_DEPARTMENT = 'department';

    public const AUDIENCE_EMPLOYEE = 'employee';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function audienceTypes(): array
    {
        return [self::AUDIENCE_COMPANY, self::AUDIENCE_DEPARTMENT, self::AUDIENCE_EMPLOYEE];
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return [self::STATUS_DRAFT, self::STATUS_SCHEDULED, self::STATUS_PUBLISHED, self::STATUS_CANCELLED];
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /** @return BelongsTo<Employee, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'audience_department_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function targetEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'audience_employee_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'cancelled_by');
    }
}
