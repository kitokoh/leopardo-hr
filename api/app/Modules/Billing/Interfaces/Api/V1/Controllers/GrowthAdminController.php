<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Billing\Domain\Models\Partner;
use App\Modules\Billing\Domain\Models\PartnerAuditLog;
use App\Modules\Billing\Domain\Models\PartnerPayoutRequest;
use App\Modules\Billing\Infrastructure\Services\PartnerService;
use App\Modules\Billing\Interfaces\Api\V1\Requests\UpdateApplicationStatusRequest;
use App\Modules\Billing\Interfaces\Api\V1\Requests\UpdatePartnerRateRequest;
use App\Modules\Billing\Interfaces\Api\V1\Requests\UpdatePayoutStatusRequest;
use App\Modules\Payroll\Domain\Models\Commission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrowthAdminController extends Controller
{
    public function __construct(private PartnerService $partnerService) {}

    /**
     * #8523 (gotcha 7) — identifiant du compte PLATEFORME appelant.
     *
     * Les routes `/platform/growth/*` sont derrière `auth:super_admin_api` :
     * l'utilisateur authentifié est un `SuperAdmin`, PAS un `User` tenant.
     * `Auth::id()` interroge le guard par défaut (`sanctum`, donc `users`) et
     * renvoyait donc `null` (ou, pire, l'id d'un utilisateur tenant homonyme) :
     * `PartnerAuditLog.admin_id` — clé étrangère vers `users.id` — recevait
     * alors l'id d'un `super_admins.id`, ce qui écrivait une trace d'audit
     * attribuée à la MAUVAISE personne (ou échouait en violation de FK).
     *
     * On résout explicitement le guard plateforme. Retour `null` = pas de
     * compte plateforme sur la requête : l'appelant doit refuser l'écriture
     * (fail-closed), jamais fabriquer un acteur.
     */
    private function platformAdminId(Request $request): ?int
    {
        $admin = $request->user('super_admin_api');

        if (! $admin instanceof SuperAdmin) {
            return null;
        }

        $id = $admin->getKey();

        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    /**
     * List all partners for the platform.
     */
    public function partners(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 25), 100));

        $partners = Partner::with('user:id,first_name,last_name,email')
            ->withCount('referredCompanies')
            ->paginate($perPage);

        // Pagination (#1703) : `data` reste une liste simple (contrat
        // historique), les métadonnées de page sont exposées dans `meta`.
        return new JsonResponse([
            'data' => $partners->items(),
            'meta' => [
                'current_page' => $partners->currentPage(),
                'per_page' => $partners->perPage(),
                'total' => $partners->total(),
                'last_page' => $partners->lastPage(),
            ],
        ]);
    }

    /**
     * Update partner commission rate with audit log.
     */
    public function updateRate(UpdatePartnerRateRequest $request, Partner $partner): JsonResponse
    {
        $validated = $request->validated();

        $adminId = $this->platformAdminId($request);
        if ($adminId === null) {
            return new JsonResponse(['error' => 'PLATFORM_ACCOUNT_REQUIRED'], 403);
        }

        $this->partnerService->updatePartnerRate(
            $partner,
            $validated['rate'],
            $adminId,
            $validated['reason']
        );

        return new JsonResponse(['success' => true]);
    }

    /**
     * List payout requests.
     */
    public function payouts(): JsonResponse
    {
        $requests = PartnerPayoutRequest::with('partner.user')
            ->latest()
            ->get();

        return new JsonResponse(['data' => $requests]);
    }

    /**
     * Update payout request status with audit.
     */
    public function updatePayoutStatus(UpdatePayoutStatusRequest $request, PartnerPayoutRequest $payout): JsonResponse
    {
        $validated = $request->validated();

        $adminId = $this->platformAdminId($request);
        if ($adminId === null) {
            return new JsonResponse(['error' => 'PLATFORM_ACCOUNT_REQUIRED'], 403);
        }

        $this->partnerService->updatePayoutStatus(
            $payout,
            $validated['status'],
            $adminId,
            $validated['notes'] ?? 'Status updated by admin.'
        );

        return new JsonResponse(['success' => true]);
    }

    /**
     * Approve or Reject an application.
     */
    public function updateApplicationStatus(UpdateApplicationStatusRequest $request, Partner $partner): JsonResponse
    {
        $validated = $request->validated();

        $adminId = $this->platformAdminId($request);

        if ($adminId === null) {
            return new JsonResponse(['error' => 'PLATFORM_ACCOUNT_REQUIRED'], 403);
        }

        if ($validated['status'] === 'approved') {
            $this->partnerService->approve($partner, $adminId);
        } else {
            $this->partnerService->reject($partner, $adminId, $request->input('reason', 'Application rejected by admin.'));
        }

        return new JsonResponse(['success' => true]);
    }

    /**
     * List recent commissions and audits.
     */
    public function history(): JsonResponse
    {
        return new JsonResponse([
            'commissions' => Commission::with(['partner.user', 'company'])->latest()->take(50)->get(),
            'audit_logs' => PartnerAuditLog::latest()->take(50)->get(),
        ]);
    }
}
