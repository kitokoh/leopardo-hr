<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
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
}

