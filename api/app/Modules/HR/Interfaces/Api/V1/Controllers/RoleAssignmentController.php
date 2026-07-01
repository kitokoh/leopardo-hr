<?php

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\RoleAssignmentMail;
use App\Core\Auth\Domain\Models\Employee;
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
            return response()->json(['message' => 'Only the company admin can assign roles.'], 403);
        }

        // Must be same company
        if ($actor->company_id !== $employee->company_id) {
            return response()->json(['message' => 'Employee not found in your company.'], 404);
        }

        $validated = $request->validate([
            'manager_role' => [
                'nullable',
                Rule::in(['rh', 'comptable', 'marketing', 'dept', 'principal']),
            ],
        ]);

        $previousRole = $employee->manager_role;
        $newRole = $validated['manager_role'];

        // Update role
        $employee->role = $newRole ? 'manager' : 'employee';
        $employee->manager_role = $newRole;
        $employee->save();

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
                ? "Role '{$newRole}' assigned successfully. An email with app download links has been sent."
                : "Role removed. Employee is now a regular employee.",
            'data' => [
                'employee_id' => $employee->id,
                'role'        => $employee->role,
                'manager_role' => $employee->manager_role,
                'app_links'   => $newRole ? RoleInvitationService::getAppDownloadLink('manager', $newRole) : null,
                'email_sent'  => (bool) $newRole,
            ],
        ]);
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
            return response()->json(['message' => 'Only the company admin can view team roles.'], 403);
        }

        $employees = Employee::where('company_id', $actor->company_id)
            ->where('status', '!=', 'archived')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'role', 'manager_role', 'status', 'photo_path']);

        return response()->json([
            'data' => $employees->map(fn (Employee $e) => [
                'id'           => $e->id,
                'name'         => trim("{$e->first_name} {$e->last_name}"),
                'email'        => $e->email,
                'role'         => $e->role,
                'manager_role' => $e->manager_role,
                'role_label'   => $e->manager_role
                    ? RoleInvitationService::getRoleLabel($e->manager_role)
                    : ($e->isManager() ? 'Manager' : 'EmployÃ©'),
                'status'       => $e->status,
                'photo_path'   => $e->photo_path,
            ]),
        ]);
    }
}
