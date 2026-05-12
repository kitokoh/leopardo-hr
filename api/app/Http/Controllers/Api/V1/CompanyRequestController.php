<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CompanyRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class CompanyRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        $requests = $user->companyRequests()
            ->latest()
            ->get()
            ->map(fn (CompanyRequest $r) => [
                'id' => $r->id,
                'company_name' => $r->company_name,
                'sector' => $r->sector,
                'country' => $r->country,
                'city' => $r->city,
                'email' => $r->email,
                'status' => $r->status,
                'admin_notes' => $r->admin_notes,
                'reviewed_at' => $r->reviewed_at?->toIso8601String(),
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return new JsonResponse(['data' => $requests]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:200'],
            'sector' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $this->resolveRequestUser($request);

        $pending = $user->companyRequests()->where('status', 'pending')->count();
        if ($pending >= 3) {
            return new JsonResponse([
                'error' => 'TOO_MANY_PENDING_REQUESTS',
                'message' => 'Vous avez deja 3 demandes en attente.',
            ], 422);
        }

        $payload = $validated;
        /** @var Employee $employee */
        $employee = $request->user();
        if ($employee instanceof Employee) {
            $payload['employee_id'] = $employee->id;
        }

        if (Schema::hasColumn('company_requests', 'manager_name')) {
            $payload['manager_name'] = $user->fullName();
        }

        if (Schema::hasColumn('company_requests', 'manager_phone') && ! empty($validated['phone'])) {
            $payload['manager_phone'] = $validated['phone'];
        }

        if (Schema::hasColumn('company_requests', 'notes') && ! empty($validated['description'])) {
            $payload['notes'] = $validated['description'];
        }

        $companyRequest = $user->companyRequests()->create($payload);

        return new JsonResponse([
            'data' => [
                'id' => $companyRequest->id,
                'company_name' => $companyRequest->company_name,
                'status' => $companyRequest->status,
                'created_at' => $companyRequest->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        $companyRequest = $user->companyRequests()->findOrFail($id);

        return new JsonResponse([
            'data' => [
                'id' => $companyRequest->id,
                'company_name' => $companyRequest->company_name,
                'sector' => $companyRequest->sector,
                'country' => $companyRequest->country,
                'city' => $companyRequest->city,
                'email' => $companyRequest->email,
                'phone' => $companyRequest->phone,
                'description' => $companyRequest->description,
                'status' => $companyRequest->status,
                'admin_notes' => $companyRequest->admin_notes,
                'reviewed_at' => $companyRequest->reviewed_at?->toIso8601String(),
                'created_at' => $companyRequest->created_at?->toIso8601String(),
            ],
        ]);
    }

    private function resolveRequestUser(Request $request): User
    {
        $user = $request->user('user_api');
        if ($user instanceof User) {
            return $user;
        }

        /** @var Employee $employee */
        $employee = $request->user();
        if ($employee instanceof Employee) {
            return User::firstOrCreate(
                ['email' => $employee->email],
                [
                    'first_name' => $employee->first_name,
                    'last_name' => $employee->last_name,
                    'password_hash' => $employee->password_hash ?: Hash::make(str()->random(32)),
                    'provider' => 'employee',
                    'preferred_language' => $employee->preferred_language ?? 'fr',
                    'status' => $employee->status ?? 'active',
                ]
            );
        }

        abort(401);
    }
}
