<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Events\EmployeeRoleAssigned;
use App\Http\Controllers\Controller;
use App\Mail\RoleAssignmentMail;
use App\Modules\HR\Infrastructure\Services\RoleInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class RoleAssignmentController extends Controller
{
    /**
     * Assign a manager_role to an employee.
     * Only accessible by principal managers.
     *
     * POST /api/v1/employees/{employee}/assign-role
     */
    public function assign(Request $request, Employee $employee): JsonResponse
    {
        $actor = $request->user();

        // Only principal can assign roles
        if (! $actor->isPrincipal()) {
            return response()->json(['message' => __('employees.role_assign_forbidden')], 403);
        }

        // Must be same company
        if ($actor->company_id !== $employee->company_id) {
            return response()->json(['message' => __('employees.role_assign_not_in_company')], 404);
        }

        $validated = $request->validate([
            'manager_role' => [
                'nullable',
                Rule::in(['rh', 'comptable', 'marketing', 'dept', 'principal']),
            ],
        ]);

        $previousRole = $employee->manager_role;
        $newRole = $validated['manager_role'];

        // #9093 — garde du DERNIER PRINCIPAL, miroir exact de
        // `PlatformTeamController::activeSuperAdminCount()` (#LAST_SUPER_ADMIN_REQUIRED).
        // Sans elle, le principal d'un tenant peut se rétrograder lui-même (ou
        // être rétrogradé) et laisser l'entreprise sans personne capable de
        // nommer un successeur : auto-verrouillage définitif. Un principal
        // SUSPENDU ne compte pas — il ne peut plus rouvrir la porte.
        if ($previousRole === 'principal' && $newRole !== 'principal'
            && $this->activePrincipalCount((string) $employee->company_id) <= 1) {
            return response()->json([
                'error' => 'LAST_PRINCIPAL_REQUIRED',
                'message' => __('errors.LAST_PRINCIPAL_REQUIRED'),
            ], 422);
        }

        // Update role
        $employee->role = $newRole ? 'manager' : 'employee';
        $employee->manager_role = $newRole;
        $employee->save();

        // PA2-MOB-007 — every nomination/revocation must leave an audit trail.
        EmployeeRoleAssigned::dispatch($employee, $actor, $previousRole, $newRole);

        // Send notification email if role was assigned (not removed)
        if ($newRole && $newRole !== $previousRole) {
            Mail::to($employee->email)->queue(new RoleAssignmentMail(
                company: $actor->company,
                employee: $employee,
                assignedByName: trim("{$actor->first_name} {$actor->last_name}"),
                newManagerRole: $newRole,
            ));
        }

        return response()->json([
            'message' => $newRole
                ? __('employees.role_assigned', ['role' => $newRole])
                : __('employees.role_removed'),
            'data' => [
                'employee_id' => $employee->id,
                'role' => $employee->role,
                'manager_role' => $employee->manager_role,
                'app_links' => $newRole ? RoleInvitationService::getAppDownloadLink('manager', $newRole) : null,
                'email_sent' => (bool) $newRole,
            ],
        ]);
    }

    /**
     * Nombre de `principal` encore ACTIFS dans l'entreprise. C'est la seule
     * mesure qui protège contre l'auto-verrouillage : un principal suspendu ne
     * peut plus nommer de successeur. Calqué sur `activeSuperAdminCount()`.
     */
    private function activePrincipalCount(string $companyId): int
    {
        return Employee::query()
            ->where('company_id', $companyId)
            ->where('manager_role', 'principal')
            ->where(function ($query): void {
                $query->where('status', 'active')->orWhereNull('status');
            })
            ->count();
    }

    /**
     * List employees with their roles for the company admin.
     * Only accessible by principal managers.
     *
     * GET /api/v1/company/team-roles
     */
    public function teamRoles(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! $actor->isPrincipal()) {
            return response()->json(['message' => __('employees.team_roles_forbidden')], 403);
        }

        $employees = Employee::where('company_id', $actor->company_id)
            ->whereNotIn('status', ['archived', 'departed'])
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'role', 'manager_role', 'status', 'photo_path']);

        return response()->json([
            'data' => $employees->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => trim("{$e->first_name} {$e->last_name}"),
                'email' => $e->email,
                'role' => $e->role,
                'manager_role' => $e->manager_role,
                'role_label' => $e->manager_role
                    ? RoleInvitationService::getRoleLabel($e->manager_role)
                    : ($e->isManager() ? __('employees.role_manager') : __('employees.role_employee')),
                'status' => $e->status,
                'photo_path' => $e->photo_path,
            ]),
        ]);
    }
}
