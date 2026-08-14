<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Events\TaxRateApproved;
use App\Events\TaxRateRejected;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SocialContributionResource;
use App\Http\Resources\Api\V1\TaxSlabResource;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\TaxRateValidationService;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
/**
 * ADMIN-PAIE (issue #1813) — seconde signature du workflow de validation des
 * taux légaux : approbation / rejet par le platform admin (guard
 * `super_admin_api`), cross-tenant par nature (le super-admin révise les
 * propositions de tous les clients).
 */
class PlatformTaxRateApprovalController extends Controller
{
    public function __construct(private readonly TaxRateValidationService $service) {}

    // ── Barèmes fiscaux ────────────────────────────────────────────────────

    public function pendingTaxSlabs(): JsonResponse
    {
        $pending = TaxSlab::query()
            ->where('status', TaxSlab::STATUS_PENDING)
            ->orderBy('country_code')
            ->orderBy('effective_from')
            ->get();

        return TaxSlabResource::collection($pending)->response();
    }

    public function approveTaxSlab(Request $request, TaxSlab $taxSlab): JsonResponse
    {
        $adminId = $this->adminId($request);

        try {
            $slab = $this->service->approve($taxSlab, $adminId);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        TaxRateApproved::dispatch($slab->getTable(), (int) $slab->id, $adminId);

        return (new TaxSlabResource($slab))->response();
    }

    public function rejectTaxSlab(Request $request, TaxSlab $taxSlab): JsonResponse
    {
        $adminId = $this->adminId($request);
        $reason = $this->validatedReason($request);

        try {
            $slab = $this->service->reject($taxSlab, $adminId, $reason);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        TaxRateRejected::dispatch($slab->getTable(), (int) $slab->id, $adminId, $reason);

        return (new TaxSlabResource($slab))->response();
    }

    // ── Cotisations sociales ───────────────────────────────────────────────

    public function pendingSocialContributions(): JsonResponse
    {
        $pending = SocialContribution::query()
            ->where('status', SocialContribution::STATUS_PENDING)
            ->orderBy('country_code')
            ->orderBy('code')
            ->get();

        return SocialContributionResource::collection($pending)->response();
    }

    public function approveSocialContribution(Request $request, SocialContribution $socialContribution): JsonResponse
    {
        $adminId = $this->adminId($request);

        try {
            $contribution = $this->service->approve($socialContribution, $adminId);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        TaxRateApproved::dispatch($contribution->getTable(), (int) $contribution->id, $adminId);

        return (new SocialContributionResource($contribution))->response();
    }

    public function rejectSocialContribution(Request $request, SocialContribution $socialContribution): JsonResponse
    {
        $adminId = $this->adminId($request);
        $reason = $this->validatedReason($request);

        try {
            $contribution = $this->service->reject($socialContribution, $adminId, $reason);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        TaxRateRejected::dispatch($contribution->getTable(), (int) $contribution->id, $adminId, $reason);

        return (new SocialContributionResource($contribution))->response();
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function adminId(Request $request): int
    {
        $admin = $request->user();
        if (! $admin instanceof SuperAdmin) {
            throw new RuntimeException('Authentification platform admin requise.');
        }

        return (int) $admin->getAuthIdentifier();
    }

    private function validatedReason(Request $request): string
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        return (string) $validated['reason'];
    }
}
