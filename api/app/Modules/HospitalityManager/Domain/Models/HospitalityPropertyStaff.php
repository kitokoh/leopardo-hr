<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Affectation d'un employé à un établissement — HOSP-003 (#7945, BC-32).
 *
 * Référence PAR VALEUR : le module RH reste propriétaire des employés.
 * Le `role` est un libellé MÉTIER descriptif (réceptionniste, gouvernante,
 * gérant…) — jamais une source d'autorisation (spec §4 : l'autorisation
 * passe par `EmployeeResourceAssignment` / resource `hospitality_property`).
 *
 * Retrait = soft delete ; ré-affectation = restauration de la ligne
 * (l'unicité couvre aussi les lignes supprimées).
 *
 * @property int $id
 * @property string $company_id
 * @property int $property_id
 * @property int $employee_id
 * @property string|null $role
 * @property Carbon|null $assigned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @mixin Builder<static>
 */
class HospitalityPropertyStaff extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $table = 'hospitality_property_staff';

    protected $fillable = [
        'company_id',
        'property_id',
        'employee_id',
        'role',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'employee_id' => 'integer',
        'property_id' => 'integer',
    ];

    /**
     * @return BelongsTo<HospitalityProperty, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(HospitalityProperty::class, 'property_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
