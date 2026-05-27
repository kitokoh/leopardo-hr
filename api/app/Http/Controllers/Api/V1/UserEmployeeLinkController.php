<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserEmployeeLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Api\V1\Auth\LinkByEmailUserEmployeeLinkRequest;

class UserEmployeeLinkController extends Controller
{
    public function linkByEmail(LinkByEmailUserEmployeeLinkRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var Employee $manager */
        $manager = $request->user();

        if (! $manager->isManager()) {
            return new JsonResponse([
                'error' => 'FORBIDDEN',
                'message' => 'Seul un manager peut lier un utilisateur.',
            ], 403);
        }

        /** @var User|null $user */
        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return new JsonResponse([
                'error' => 'USER_NOT_FOUND',
                'message' => 'Aucun compte ordinaire trouve avec cet email.',
            ], 404);
        }

        $existing = UserEmployeeLink::where('user_id', $user->id)
            ->where('company_id', $manager->company_id)
            ->first();

        if ($existing) {
            return new JsonResponse([
                'error' => 'ALREADY_LINKED',
                'message' => 'Cet utilisateur est deja lie a votre entreprise.',
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
