<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyRequest;
use App\Models\SuperAdmin;
use App\Services\CompanyProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Http\Requests\Api\V1\Platform\PlatformCompanyRequestIndexRequest;
use App\Http\Requests\Api\V1\Platform\UpdateStatusPlatformCompanyRequestRequest;

class PlatformCompanyRequestController extends Controller
{
    public function __construct(
        private readonly CompanyProvisioningService $companyProvisioningService,
    ) {}

    public function index(PlatformCompanyRequestIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = CompanyRequest::with('user:id,first_name,last_name,email')
            ->latest();

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['search']) && trim($validated['search']) !== '') {
            $search = trim($validated['search']);
            $query->where(function ($inner) use ($search): void {
                $inner
                    ->where('company_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
            });
        }

        $requests = $query->paginate((int) ($validated['per_page'] ?? 20));

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

    public function updateStatus(UpdateStatusPlatformCompanyRequestRequest $request, int $id): JsonResponse
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        $validated = $request->validated();

        $companyRequest = CompanyRequest::with('user:id,first_name,last_name,email,phone')->findOrFail($id);

        if (! $companyRequest->isPending()) {
            return new JsonResponse([
                'error' => 'ALREADY_REVIEWED',
                'message' => 'Cette demande a deja ete traitee.',
            ], 422);
        }

        $approvedCompany = null;
        if ($validated['status'] === 'approved') {
            /** @var SuperAdmin $superAdmin */
            $superAdmin = $request->user('super_admin_api');
            $approvedCompany = $this->provisionCompanyFromRequest(
                companyRequest: $companyRequest,
                superAdmin: $superAdmin,
                planId: $validated['plan_id'] ?? null,
                notes: $validated['admin_notes'] ?? null,
            );
        }

        $companyRequest->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? null,
            'approved_company_id' => $approvedCompany?->id,
            'reviewed_at' => now(),
        ]);

        return new JsonResponse([
            'data' => [
                'id' => $companyRequest->id,
                'status' => $companyRequest->status,
                'approved_company_id' => $companyRequest->approved_company_id,
                'admin_notes' => $companyRequest->admin_notes,
                'reviewed_at' => $companyRequest->reviewed_at?->toIso8601String(),
            ],
        ]);
    }

    private function provisionCompanyFromRequest(
        CompanyRequest $companyRequest,
        SuperAdmin $superAdmin,
        ?int $planId,
        ?string $notes,
    ): Company {
        $resolvedPlanId = $planId
            ?? DB::table('plans')->where('is_active', true)->orderBy('id')->value('id')
            ?? DB::table('plans')->orderBy('id')->value('id');

        if (! $resolvedPlanId) {
            abort(422, 'Aucun plan actif disponible pour approuver cette demande.');
        }

        $managerName = trim($companyRequest->manager_name ?: $companyRequest->user?->fullName() ?: 'Manager principal');
        $nameParts = preg_split('/\s+/', $managerName, 2) ?: ['Manager'];

        $email = $companyRequest->email ?: $companyRequest->user?->email;
        if (! $email) {
            abort(422, 'Un email de contact est requis pour approuver cette demande.');
        }

        $country = strtoupper((string) ($companyRequest->country ?: 'DZ'));
        if (strlen($country) !== 2) {
            $country = 'DZ';
        }

        $result = $this->companyProvisioningService->provisionSharedCompany([
            'name' => $companyRequest->company_name,
            'sector' => $companyRequest->sector ?: 'Non precise',
            'country' => $country,
            'city' => $companyRequest->city ?: 'Non precise',
            'email' => $email,
            'phone' => $companyRequest->phone ?: $companyRequest->user?->phone,
            'plan_id' => $resolvedPlanId,
            'notes' => $notes ?: $companyRequest->description,
            'manager_first_name' => $nameParts[0] ?: 'Manager',
            'manager_last_name' => $nameParts[1] ?? 'Principal',
            'manager_email' => $companyRequest->user?->email ?: $email,
            'manager_phone' => $companyRequest->manager_phone ?: $companyRequest->user?->phone ?: $companyRequest->phone,
        ], $superAdmin);

        return $result['company'];
    }
}
