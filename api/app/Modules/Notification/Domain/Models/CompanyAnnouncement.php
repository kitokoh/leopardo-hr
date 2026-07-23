<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Domain\Models\Department;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PA2-COMM-004 — Company-scoped announcement broadcast by a manager to the
 * whole company, one department, or a single employee.
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
 * @property Carbon|null $published_at
 * @property Carbon|null $expires_at
 * @property int $recipients_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
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
        'published_at',
        'expires_at',
        'recipients_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const AUDIENCE_COMPANY = 'company';

    public const AUDIENCE_DEPARTMENT = 'department';

    public const AUDIENCE_EMPLOYEE = 'employee';

    /** @return list<string> */
    public static function audienceTypes(): array
    {
        return [self::AUDIENCE_COMPANY, self::AUDIENCE_DEPARTMENT, self::AUDIENCE_EMPLOYEE];
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
}
