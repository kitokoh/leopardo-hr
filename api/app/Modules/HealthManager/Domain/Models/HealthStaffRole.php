<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Rôle opérationnel non médical (réception, facturation) — Issue #7786.
 *
 * Porté par un employé RH du tenant (`employee_id`, sans FK dure — pattern
 * EduTeacher). Rôle borné (reception|billing, CHECK en base), unique par
 * employé et par tenant. Détermine les rôles RBAC `health.reception` et
 * `health.billing` (HealthAccess).
 *
 * @property int $id
 * @property string $company_id
 * @property int $employee_id
 * @property string $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthStaffRole extends Model
{
    use BelongsToCompany;

    public const ROLE_RECEPTION = 'reception';

    public const ROLE_BILLING = 'billing';

    /** @var list<string> */
    public const ROLES = [
        self::ROLE_RECEPTION,
        self::ROLE_BILLING,
    ];

    protected $table = 'health_staff_roles';

    protected $fillable = [
        'company_id',
        'employee_id',
        'role',
    ];

    protected $casts = [
        'employee_id' => 'integer',
        'role' => 'string',
    ];
}
