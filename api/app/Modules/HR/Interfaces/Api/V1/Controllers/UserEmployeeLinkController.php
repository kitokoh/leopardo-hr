<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\EmployeeJoinRequest;
use App\Modules\HR\Domain\Models\UserEmployeeLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserEmployeeLinkController extends Controller
{
    public function linkByEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'employee_id' => ['required', 'integer'],
        ]);

        /** @var Employee $manager */
        $manager = $request->user();

        if (! $manager->isManager()) {
            return new JsonResponse([
                'error' => 'FORBIDDEN',
                'message' => __('errors.MANAGER_ONLY_LINK'),
            ], 403);
        }

        /** @var User|null $user */
        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return new JsonResponse([
                'error' => 'USER_NOT_FOUND',
                'message' => __('errors.ORDINARY_ACCOUNT_NOT_FOUND'),
            ], 404);
        }

        // Issue #3065 (QA 2026-08-15) : l'employee_id doit appartenir à la
        // société de l'acteur — un manager ne peut pas lier un utilisateur à
        // un employé d'une autre entreprise (lien cross-tenant interdit).
        // 404 (et non 422) : pas de fuite d'existence cross-tenant.
        $employee = Employee::query()
            ->where('id', $validated['employee_id'])
            ->where('company_id', $manager->company_id)
            ->first();

        if (! $employee) {
            return new JsonResponse([
                'error' => 'EMPLOYEE_NOT_FOUND',
                'message' => __('errors.EMPLOYEE_NOT_FOUND_IN_COMPANY'),
            ], 404);
        }

        $existing = UserEmployeeLink::where('user_id', $user->id)
            ->where('company_id', $manager->company_id)
            ->first();

        if ($existing) {
            return new JsonResponse([
                'error' => 'ALREADY_LINKED',
                'message' => __('errors.USER_ALREADY_LINKED'),
            ], 409);
        }

        $link = UserEmployeeLink::create([
            'user_id' => $user->id,
            'employee_id' => $validated['employee_id'],
            'company_id' => $manager->company_id,
            'status' => 'active',
            'linked_at' => now(),
        ]);

        return new JsonResponse([
            'data' => [
                'id' => $link->id,
                'user_email' => $user->email,
                'user_name' => $user->fullName(),
                'status' => $link->status,
                'linked_at' => $link->linked_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function companyDirectory(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $companies = Company::query()
            ->where('status', 'active')
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('sector', 'like', "%{$search}%");
            }))
            ->select(['id', 'name', 'slug', 'sector', 'city'])
            ->orderBy('name')
            ->limit(30)
            ->get();

        return new JsonResponse(['data' => $companies]);
    }

    public function requestToJoin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var User $user */
        $user = $request->user('user_api');
        $existing = EmployeeJoinRequest::query()
            ->where('user_id', $user->id)
            ->where('company_id', $validated['company_id'])
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return new JsonResponse(['error' => 'JOIN_REQUEST_ALREADY_PENDING'], 409);
        }

        $joinRequest = EmployeeJoinRequest::create([
            'user_id' => $user->id,
            'company_id' => $validated['company_id'],
            'message' => $validated['message'] ?? null,
            'status' => 'pending',
        ]);

        return new JsonResponse([
            'data' => [
                'id' => $joinRequest->id,
                'company_id' => $joinRequest->company_id,
                'status' => $joinRequest->status,
            ],
        ], 201);
    }

    public function myJoinRequests(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('user_api');
        $requests = EmployeeJoinRequest::query()
            ->with('company:id,name,slug')
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (EmployeeJoinRequest $joinRequest) => [
                'id' => $joinRequest->id,
                'company_id' => $joinRequest->company_id,
                'company_name' => $joinRequest->company?->name,
                'status' => $joinRequest->status,
                'created_at' => $joinRequest->created_at?->toIso8601String(),
            ]);

        return new JsonResponse(['data' => $requests]);
    }

    public function myLinks(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('user_api');

        $links = $user->employeeLinks()
            ->with('company:id,name')
            ->get()
            ->map(fn (UserEmployeeLink $link) => [
                'id' => $link->id,
                'company_id' => $link->company_id,
                'company_name' => $link->company?->name,
                'employee_id' => $link->employee_id,
                'status' => $link->status,
                'linked_at' => $link->linked_at?->toIso8601String(),
            ]);

        return new JsonResponse(['data' => $links]);
    }

    public function approveJoinRequest(Request $request, EmployeeJoinRequest $joinRequest): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer'],
        ]);

        /** @var Employee $manager */
        $manager = $request->user();
        if (! $manager->isManager() || (string) $manager->company_id !== (string) $joinRequest->company_id) {
            return new JsonResponse(['error' => 'FORBIDDEN'], 403);
        }

        $employee = Employee::query()
            ->where('id', $validated['employee_id'])
            ->where('company_id', $manager->company_id)
            ->first();
        if (! $employee) {
            return new JsonResponse(['error' => 'EMPLOYEE_NOT_FOUND_IN_COMPANY'], 404);
        }

        $link = UserEmployeeLink::updateOrCreate(
            ['user_id' => $joinRequest->user_id, 'company_id' => $joinRequest->company_id],
            ['employee_id' => $employee->id, 'status' => 'active', 'linked_at' => now()],
        );
        $joinRequest->update([
            'status' => 'approved',
            'approved_employee_id' => $employee->id,
            'reviewed_by' => $manager->id,
            'reviewed_at' => now(),
        ]);

        return new JsonResponse(['data' => [
            'id' => $link->id,
            'status' => 'active',
            'pointage_enabled' => true,
        ]]);
    }
}

