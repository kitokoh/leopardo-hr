<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CompanyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformCompanyRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CompanyRequest::with('user:id,first_name,last_name,email')
            ->latest();

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->paginate(20);

        return new JsonResponse([
            'data' => $requests->map(fn (CompanyRequest $r) => [
                'id' => $r->id,
                'company_name' => $r->company_name,
                'sector' => $r->sector,
                'country' => $r->country,
                'city' => $r->city,
                'email' => $r->email,
                'phone' => $r->phone,
                'description' => $r->description,
                'status' => $r->status,
                'admin_notes' => $r->admin_notes,
                'user' => $r->user ? [
                    'id' => $r->user->id,
                    'name' => $r->user->fullName(),
                    'email' => $r->user->email,
                ] : null,
                'created_at' => $r->created_at?->toIso8601String(),
                'reviewed_at' => $r->reviewed_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $request = CompanyRequest::with('user:id,first_name,last_name,email')
            ->findOrFail($id);

        return new JsonResponse([
            'data' => [
                'id' => $request->id,
                'company_name' => $request->company_name,
                'sector' => $request->sector,
                'country' => $request->country,
                'city' => $request->city,
                'email' => $request->email,
                'phone' => $request->phone,
                'description' => $request->description,
                'status' => $request->status,
                'admin_notes' => $request->admin_notes,
                'user' => $request->user ? [
                    'id' => $request->user->id,
                    'name' => $request->user->fullName(),
                    'email' => $request->user->email,
                ] : null,
                'created_at' => $request->created_at?->toIso8601String(),
                'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            ],
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $companyRequest = CompanyRequest::findOrFail($id);

        if (! $companyRequest->isPending()) {
            return new JsonResponse([
                'error' => 'ALREADY_REVIEWED',
                'message' => 'Cette demande a deja ete traitee.',
            ], 422);
        }

        $companyRequest->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? null,
            'reviewed_at' => now(),
        ]);

        return new JsonResponse([
            'data' => [
                'id' => $companyRequest->id,
                'status' => $companyRequest->status,
                'admin_notes' => $companyRequest->admin_notes,
                'reviewed_at' => $companyRequest->reviewed_at?->toIso8601String(),
            ],
        ]);
    }
}
