<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Domain\Models\PlatformImpersonationSession;
use App\Modules\Platform\Infrastructure\Services\ImpersonationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PA2-ADM-006 — Super-admin "log in as this employee" support tool.
 *
 * Every session requires a mandatory reason, is time-limited (30 min by
 * default, 120 min max), and is fully audited: who impersonated, whom,
 * why, and when it started/ended. See ImpersonationService for the token
 * issuance/revocation mechanics.
 */
class PlatformImpersonationController extends Controller
{
    public function __construct(
        private readonly ImpersonationService $impersonationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 20)));

        $query = PlatformImpersonationSession::query()->with('superAdmin');

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $sessions = $query->orderByDesc('created_at')->paginate($perPage);

        return new JsonResponse([
            'data' => $sessions->getCollection()->map(fn (PlatformImpersonationSession $session): array => $this->serialize($session))->all(),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'uuid'],
            'employee_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'ttl_minutes' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        /** @var SuperAdmin $admin */
        $admin = $request->user('super_admin_api');

        $result = $this->impersonationService->start(
            superAdmin: $admin,
            companyId: $data['company_id'],
            employeeId: $data['employee_id'],
            reason: $data['reason'],
            ipAddress: $request->ip(),
            ttlMinutes: $data['ttl_minutes'] ?? null,
        );

        return new JsonResponse([
            'data' => $this->serialize($result['session']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at']->toIso8601String(),
        ], 201);
    }

    public function destroy(Request $request, PlatformImpersonationSession $session): JsonResponse
    {
        /** @var SuperAdmin $admin */
        $admin = $request->user('super_admin_api');

        $this->impersonationService->end($session, $admin);

        return new JsonResponse(['status' => 'ok']);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PlatformImpersonationSession $session): array
    {
        return [
            'id' => $session->id,
            'super_admin_id' => $session->super_admin_id,
            'super_admin_name' => $session->superAdmin?->name,
            'company_id' => $session->company_id,
            'company_name' => $session->company_name,
            'employee_id' => $session->employee_id,
            'employee_name' => $session->employee_name,
            'employee_email' => $session->employee_email,
            'reason' => $session->reason,
            'ip_address' => $session->ip_address,
            'expires_at' => $session->expires_at->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'is_active' => $session->isActive(),
            'created_at' => $session->created_at?->toIso8601String(),
        ];
    }
}
