<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * PA2-COMM-012 — Pilot client support ticket/conversation opened by a tenant
 * manager or employee and triaged by a platform super-admin (status +
 * priority), mirroring `PlatformAnnouncement` (PA2-COMM-005): this table
 * lives in the `public` schema because a ticket is owned by the platform,
 * not by a single tenant's own database scope.
 *
 * @property int $id
 * @property string $company_id
 * @property int $created_by_employee_id
 * @property string $subject
 * @property string $category
 * @property string $priority
 * @property string $status
 * @property int|null $assigned_super_admin_id
 * @property Carbon|null $last_message_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $company
 * @property-read Employee $createdBy
 * @property-read SuperAdmin|null $assignedSuperAdmin
 * @property-read Collection<int, PlatformSupportMessage> $messages
 *
 * @mixin Builder<static>
 */
class PlatformSupportTicket extends Model
{
    protected $table = 'platform_support_tickets';

    protected $fillable = [
        'company_id',
        'created_by_employee_id',
        'subject',
        'category',
        'priority',
        'status',
        'assigned_super_admin_id',
        'last_message_at',
        'resolved_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_BILLING = 'billing';

    public const CATEGORY_TECHNICAL = 'technical';

    public const CATEGORY_ONBOARDING = 'onboarding';

    public const CATEGORY_OTHER = 'other';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const STATUS_OPEN = 'open';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    /** @return list<string> */
    public static function categories(): array
    {
        return [
            self::CATEGORY_GENERAL,
            self::CATEGORY_BILLING,
            self::CATEGORY_TECHNICAL,
            self::CATEGORY_ONBOARDING,
            self::CATEGORY_OTHER,
        ];
    }

    /** @return list<string> */
    public static function priorities(): array
    {
        return [self::PRIORITY_LOW, self::PRIORITY_NORMAL, self::PRIORITY_HIGH, self::PRIORITY_URGENT];
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return [self::STATUS_OPEN, self::STATUS_PENDING, self::STATUS_RESOLVED, self::STATUS_CLOSED];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by_employee_id');
    }

    /** @return BelongsTo<SuperAdmin, $this> */
    public function assignedSuperAdmin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'assigned_super_admin_id');
    }

    /** @return HasMany<PlatformSupportMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(PlatformSupportMessage::class, 'platform_support_ticket_id')->orderBy('created_at');
    }

    public function isOpenForReplies(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_PENDING], true);
    }
}
