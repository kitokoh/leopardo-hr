<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Affectation d'un employé RH à une succursale restaurant (#7909).
 *
 * Un employé ne peut être affecté qu'à des succursales de SON tenant
 * (validation `company_id` dans `RestaurantBranchStaffService::assign()`,
 * même pattern que `FuelShiftService::assign()`) et une seule fois par
 * succursale (unicité `(company_id, branch_id, employee_id)`). Le retrait
 * est un soft delete : l'historique des affectations reste consultable en
 * base et une ré-affectation restaure la ligne supprimée.
 *
 * @property int $id
 * @property string $company_id
 * @property int $branch_id
 * @property int $employee_id
 * @property string|null $role
 * @property Carbon|null $assigned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read RestaurantBranch|null $branch
 * @property-read Employee|null $employee
 *
 * @mixin Builder<static>
 */
class RestaurantBranchStaff extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $table = 'restaurant_branch_staff';

    protected $fillable = [
        'company_id',
        'branch_id',
        'employee_id',
        'role',
        'assigned_at',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'employee_id' => 'integer',
        'assigned_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
