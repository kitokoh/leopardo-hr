<?php

declare(strict_types=1);

namespace App\Core\Tenant\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\Auditable;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Issue #7761 (délégation d'accès, spec MISSION_ESPACE_CLIENT §3.1) — UN
 * module de l'espace client accordé à UN collaborateur.
 *
 * Complément COMPOSABLE du RBAC historique : là où `manager_role` est unique
 * par collaborateur, les grants se cumulent (« Moussa → marketing +
 * comptabilité + tickets »). Le registre des clés est fermé
 * (`App\Core\Auth\Domain\Enums\ModuleKey`) ; l'enforcement vit dans
 * `EnsureApiManagerMiddleware` (rôle autorisé OU grant) et
 * `EnsureModuleGrantMiddleware` (grant OU manager, ex. tickets support).
 *
 * `Auditable` : toute pose / révocation écrit une ligne dans `audit_logs`
 * (même doctrine qu'`EmployeeResourceAssignment`, #7598).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $employee_id
 * @property string $module_key
 * @property int|null $granted_by_employee_id
 */
class EmployeeModuleGrant extends Model
{
    use Auditable;
    use BelongsToCompany;

    /**
     * `company_id` et `granted_by_employee_id` sont délibérément ABSENTS :
     * posés explicitement par l'appelant depuis le contexte tenant (même
     * garde qu'`EmployeeResourceAssignment`, #3597 — un `company_id`
     * mass-assignable laisserait un appelant choisir le tenant d'écriture).
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'module_key',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function grantor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'granted_by_employee_id');
    }
}
