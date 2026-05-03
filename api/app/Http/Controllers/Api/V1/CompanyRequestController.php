<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CompanyRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('user_api');

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

        /** @var User $user */
        $user = $request->user('user_api');

        $pending = $user->companyRequests()->where('status', 'pending')->count();
        if ($pending >= 3) {
            return new JsonResponse([
                'error' => 'TOO_MANY_PENDING_REQUESTS',
                'message' => 'Vous avez deja 3 demandes en attente.',
            ], 422);
        }

        $companyRequest = $user->companyRequests()->create($validated);

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
        /** @var User $user */
        $user = $request->user('user_api');

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
}
