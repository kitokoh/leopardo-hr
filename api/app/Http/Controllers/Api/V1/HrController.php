<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * HrController — Leopardo RH Mobile App
 *
 * Endpoints ONLY accessible to employees with manager_role = 'rh'.
 * These routes are protected by middleware: api.manager:rh,principal
 *
 * Responsibilities of the RH role:
 * - Add employees (principal can also do this, but here scoped to RH)
 * - Manage absences, schedules for employees
 * - View team members and their contracts
 * - Manage onboarding invitations
 * - Access HR-specific reports and exports
 *
 * The RH manager CANNOT:
 * - Assign manager roles (principal only)
 * - Access billing/subscription
 * - Access platform/super-admin areas
 */
class HrController extends Controller
{
    /**
     * GET /api/v1/hr/dashboard
     * HR-specific dashboard with team stats.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $employee = $request->user();

        $company_id = $employee->company_id;

        $stats = [
            'total_active_employees' => Employee::where('company_id', $company_id)
                ->where('status', 'active')
                ->whereNot('role', 'manager')
                ->count(),

            'total_employees' => Employee::where('company_id', $company_id)
                ->whereNotIn('status', ['archived'])
                ->count(),

            'pending_invitations' => Employee::where('company_id', $company_id)
                ->where('status', 'invited')
                ->count(),

            'new_this_month' => Employee::where('company_id', $company_id)
                ->where('status', 'active')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),

            'by_contract_type' => Employee::where('company_id', $company_id)
                ->whereNotIn('status', ['archived'])
                ->selectRaw('contract_type, COUNT(*) as count')
                ->groupBy('contract_type')
                ->pluck('count', 'contract_type'),
        ];

        return response()->json([
            'data' => $stats,
            'meta' => [
                'app'  => 'rh',
                'role' => $employee->manager_role,
            ],
        ]);
    }

    /**
     * GET /api/v1/hr/employees
     * List all active employees for this company (HR view).
     * Includes contract info, status, schedule.
     */
    public function employees(Request $request): AnonymousResourceCollection
    {
        $employee = $request->user();

        $query = Employee::where('company_id', $employee->company_id)
            ->whereNotIn('status', ['archived'])
            ->with(['department', 'position', 'schedule', 'site'])
            ->orderBy('last_name');

        // Filtering
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($contract = $request->query('contract_type')) {
            $query->where('contract_type', $contract);
        }

        return EmployeeResource::collection($query->paginate(25));
    }

    /**
     * POST /api/v1/hr/employees
     * Add a new employee (HR can add employees, but not assign manager roles).
     * Manager role assignment is exclusively reserved for the principal.
     */
    public function addEmployee(Request $request): JsonResponse
    {
        $actor = $request->user();

        $validated = $request->validate([
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'required|string|max:100',
            'email'           => 'required|email|max:255|unique:employees,email',
            'personal_phone'  => 'nullable|string|max:20',
            'gender'          => ['required', Rule::in(['male', 'female', 'other'])],
            'date_of_birth'   => 'required|date|before:today',
            'contract_type'   => ['required', Rule::in(['cdi', 'cdd', 'freelance', 'intern', 'part_time'])],
            'contract_start'  => 'required|date',
            'contract_end'    => 'nullable|date|after:contract_start',
            'salary_type'     => ['required', Rule::in(['monthly', 'daily', 'hourly'])],
            'salary_base'     => 'nullable|numeric|min:0',
            'hourly_rate'     => 'nullable|numeric|min:0',
            'department_id'   => 'nullable|integer|exists:departments,id',
            'position_id'     => 'nullable|integer|exists:positions,id',
            'site_id'         => 'nullable|integer|exists:sites,id',
            'schedule_id'     => 'nullable|integer|exists:schedules,id',
        ]);

        $employee = Employee::create([
            ...$validated,
            'company_id'       => $actor->company_id,
            'role'             => 'employee',  // HR can only create regular employees
            'manager_role'     => null,         // Only principal can assign manager roles
            'status'           => 'active',
            'preferred_language' => 'fr',
        ]);

        return response()->json([
            'message' => 'Employee created successfully.',
            'data'    => new EmployeeResource($employee),
        ], 201);
    }

    /**
     * GET /api/v1/hr/employees/{employee}
     * Get details for a specific employee (HR view).
     */
    public function showEmployee(Request $request, Employee $employee): JsonResponse
    {
        $actor = $request->user();

        if ($actor->company_id !== $employee->company_id) {
            return response()->json(['message' => 'Employee not found.'], 404);
        }

        $employee->load(['department', 'position', 'schedule', 'site', 'company']);

        return response()->json([
            'data' => new EmployeeResource($employee),
        ]);
    }

    /**
     * PATCH /api/v1/hr/employees/{employee}
     * Update employee profile (HR can update, but not change role/manager_role).
     */
    public function updateEmployee(Request $request, Employee $employee): JsonResponse
    {
        $actor = $request->user();

        if ($actor->company_id !== $employee->company_id) {
            return response()->json(['message' => 'Employee not found.'], 404);
        }

        $validated = $request->validate([
            'first_name'     => 'sometimes|string|max:100',
            'last_name'      => 'sometimes|string|max:100',
            'personal_phone' => 'sometimes|nullable|string|max:20',
            'address_line'   => 'sometimes|nullable|string|max:255',
            'contract_type'  => ['sometimes', Rule::in(['cdi', 'cdd', 'freelance', 'intern', 'part_time'])],
            'contract_end'   => 'sometimes|nullable|date',
            'salary_base'    => 'sometimes|nullable|numeric|min:0',
            'hourly_rate'    => 'sometimes|nullable|numeric|min:0',
            'department_id'  => 'sometimes|nullable|integer|exists:departments,id',
            'position_id'    => 'sometimes|nullable|integer|exists:positions,id',
            'site_id'        => 'sometimes|nullable|integer|exists:sites,id',
            'schedule_id'    => 'sometimes|nullable|integer|exists:schedules,id',
            'status'         => ['sometimes', Rule::in(['active', 'inactive', 'on_leave', 'suspended'])],
        ]);

        // Ensure HR cannot escalate roles
        unset($validated['role'], $validated['manager_role']);

        $employee->update($validated);

        return response()->json([
            'message' => 'Employee updated successfully.',
            'data'    => new EmployeeResource($employee->fresh(['department', 'position', 'schedule', 'site'])),
        ]);
    }

    /**
     * GET /api/v1/hr/team-overview
     * Compact team overview for the RH dashboard home screen.
     */
    public function teamOverview(Request $request): JsonResponse
    {
        $actor = $request->user();

        $employees = Employee::where('company_id', $actor->company_id)
            ->whereNotIn('status', ['archived'])
            ->with(['department', 'position'])
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'role', 'manager_role', 'status', 'photo_path', 'contract_type', 'department_id', 'position_id']);

        return response()->json([
            'data' => $employees->map(fn (Employee $e) => [
                'id'            => $e->id,
                'name'          => trim("{$e->first_name} {$e->last_name}"),
                'email'         => $e->email,
                'photo_path'    => $e->photo_path,
                'role'          => $e->role,
                'manager_role'  => $e->manager_role,
                'status'        => $e->status,
                'contract_type' => $e->contract_type,
                'department'    => $e->department?->name,
                'position'      => $e->position?->title,
            ]),
            'meta' => [
                'total' => $employees->count(),
            ],
        ]);
    }

    /**
     * GET /api/v1/hr/me
     * Authenticated RH profile with role info and app context.
     */
    public function me(Request $request): JsonResponse
    {
        $employee = $request->user();
        $employee->load(['company', 'department', 'position']);

        return response()->json([
            'data' => [
                'id'           => $employee->id,
                'name'         => trim("{$employee->first_name} {$employee->last_name}"),
                'email'        => $employee->email,
                'role'         => $employee->role,
                'manager_role' => $employee->manager_role,
                'role_label'   => 'Responsable RH',
                'app'          => 'rh',
                'company'      => [
                    'id'   => $employee->company?->id,
                    'name' => $employee->company?->name,
                ],
                'photo_path'   => $employee->photo_path,
            ],
        ]);
    }
}
