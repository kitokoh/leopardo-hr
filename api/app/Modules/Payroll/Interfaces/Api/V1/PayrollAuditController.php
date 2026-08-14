<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Models\PayrollCalculationAudit;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Issue #1874 — audit et observabilité des calculs de paie.
 *
 * GET /api/v1/payroll/audit (manager principal/RH du tenant, isolation
 * stricte sur company_id) et GET /api/v1/payroll/audit/{correlationId}
 * (reproduction du contexte d'un calcul). Le platform_admin accède aux
 * mêmes données via /api/v1/admin/payroll/audit (cross-tenant, filtre
 * company_id optionnel). RBAC via PayrollAuditPolicy (pattern #1917).
 *
 * Lecture seule : l'audit est immuable (append-only, docs/payroll/AUDIT.md).
 */
class PayrollAuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee|SuperAdmin $actor */
        $actor = $this->actor($request);

        Gate::forUser($actor)->authorize('viewAny', PayrollCalculationAudit::class);

        $query = PayrollCalculationAudit::query();

        if ($actor instanceof Employee) {
            // Isolation tenant stricte : le manager ne voit que SA société.
            $query->where('company_id', $actor->company_id);
        } elseif ($request->filled('company_id')) {
            // Platform admin : filtre optionnel sur un tenant précis.
            $query->where('company_id', (string) $request->string('company_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('country_code')) {
            $query->where('country_code', strtoupper((string) $request->string('country_code')));
        }

        /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, PayrollCalculationAudit> $audits */
        $audits = $query->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => array_map(
                fn (PayrollCalculationAudit $audit): array => $this->serialize($audit),
                $audits->items()
            ),
            'meta' => [
                'current_page' => $audits->currentPage(),
                'last_page' => $audits->lastPage(),
                'per_page' => $audits->perPage(),
                'total' => $audits->total(),
            ],
        ]);
    }

    public function show(Request $request, string $correlationId): JsonResponse
    {
        /** @var Employee|SuperAdmin $actor */
        $actor = $this->actor($request);

        $query = PayrollCalculationAudit::query()->where('correlation_id', $correlationId);

        if ($actor instanceof Employee) {
            // Isolation tenant stricte : 404 si l'audit n'appartient pas au tenant.
            $query->where('company_id', $actor->company_id);
        }

        /** @var PayrollCalculationAudit|null $audit */
        $audit = $query->first();

        if ($audit === null) {
            abort(404);
        }

        Gate::forUser($actor)->authorize('view', $audit);

        return response()->json([
            'data' => $this->serialize($audit),
        ]);
    }

    /**
     * @return Employee|SuperAdmin
     */
    private function actor(Request $request): Employee|SuperAdmin
    {
        $user = $request->user();

        if ($user instanceof Employee || $user instanceof SuperAdmin) {
            return $user;
        }

        // Inatteignable en pratique (routes derrière auth:sanctum /
        // auth:super_admin_api + api.manager) — garde défensive.
        throw new AuthenticationException();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PayrollCalculationAudit $audit): array
    {
        return [
            'id' => $audit->id,
            'correlation_id' => $audit->correlation_id,
            'company_id' => $audit->company_id,
            'actor' => [
                'type' => $audit->actor_type,
                'id' => $audit->actor_id,
            ],
            'country_code' => $audit->country_code,
            'period_start' => $audit->period_start?->toDateString(),
            'period_end' => $audit->period_end?->toDateString(),
            'rules_version' => $audit->rules_version,
            'rules_identifier' => $audit->rules_identifier,
            'input' => $audit->input_snapshot,
            'result' => $audit->result_snapshot,
            'status' => $audit->status,
            'error_message' => $audit->error_message,
            'created_at' => $audit->created_at->toIso8601String(),
        ];
    }
}
