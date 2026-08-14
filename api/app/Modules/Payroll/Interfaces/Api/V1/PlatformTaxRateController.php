<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Application\Services\TaxRateValidationWorkflow;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxRateChangeLog;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ADMIN-PAIE (#1813) — surface platform_admin du workflow de validation des
 * taux légaux. Routes sous `/platform/payroll/tax-rates` (guard
 * `super_admin_api`) : approbation/rejet des barèmes fiscaux et cotisations
 * sociales en attente, listing cross-tenant et audit trail immuable.
 *
 * Seul un platform_admin peut approuver/rejeter ; les RH/comptables ne font
 * que créer (draft) et soumettre via `/api/v1/tax-slabs` /
 * `/api/v1/social-contributions`.
 */
class PlatformTaxRateController extends Controller
{
    public function __construct(
        private readonly TaxRateValidationWorkflow $validationWorkflow,
    ) {}

    public function slabs(Request $request): JsonResponse
    {
        $query = TaxSlab::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->when($request->filled('country_code'), fn ($q) => $q->forCountry((string) $request->string('country_code')))
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->string('company_id')->value()));

        $slabs = $query->orderByDesc('id')->limit(500)->get();

        return response()->json([
            'data' => $slabs->map(fn (TaxSlab $slab): array => $this->slabPayload($slab))->all(),
        ]);
    }

    public function contributions(Request $request): JsonResponse
    {
        $query = SocialContribution::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->when($request->filled('country_code'), fn ($q) => $q->forCountry((string) $request->string('country_code')))
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->string('company_id')->value()));

        $contributions = $query->orderByDesc('id')->limit(500)->get();

        return response()->json([
            'data' => $contributions->map(fn (SocialContribution $contribution): array => $this->contributionPayload($contribution))->all(),
        ]);
    }

    /**
     * Liste consolidée des éléments en attente de validation (barèmes +
     * cotisations), pour la section « En attente de validation » du
     * tableau de bord admin.
     */
    public function pending(): JsonResponse
    {
        $slabs = TaxSlab::query()->pendingValidation()->orderByDesc('id')->limit(200)->get();
        $contributions = SocialContribution::query()->pendingValidation()->orderByDesc('id')->limit(200)->get();

        return response()->json([
            'data' => [
                'tax_slabs' => $slabs->map(fn (TaxSlab $slab): array => $this->slabPayload($slab))->all(),
                'social_contributions' => $contributions->map(fn (SocialContribution $contribution): array => $this->contributionPayload($contribution))->all(),
            ],
        ]);
    }

    public function approveTaxSlab(Request $request, TaxSlab $taxSlab): JsonResponse
    {
        /** @var TaxSlab $slab */
        $slab = $this->validationWorkflow->approve($taxSlab, $this->superAdmin($request));

        return response()->json(['data' => $this->slabPayload($slab)]);
    }

    public function rejectTaxSlab(Request $request, TaxSlab $taxSlab): JsonResponse
    {
        /** @var TaxSlab $slab */
        $slab = $this->validationWorkflow->reject($taxSlab, $this->superAdmin($request), $this->reason($request));

        return response()->json(['data' => $this->slabPayload($slab)]);
    }

    public function approveSocialContribution(Request $request, SocialContribution $socialContribution): JsonResponse
    {
        /** @var SocialContribution $contribution */
        $contribution = $this->validationWorkflow->approve($socialContribution, $this->superAdmin($request));

        return response()->json(['data' => $this->contributionPayload($contribution)]);
    }

    public function rejectSocialContribution(Request $request, SocialContribution $socialContribution): JsonResponse
    {
        /** @var SocialContribution $contribution */
        $contribution = $this->validationWorkflow->reject($socialContribution, $this->superAdmin($request), $this->reason($request));

        return response()->json(['data' => $this->contributionPayload($contribution)]);
    }

    /**
     * Audit trail global récent (append-only) pour l'onglet « Historique ».
     */
    public function history(Request $request): JsonResponse
    {
        $query = TaxRateChangeLog::query()
            ->when($request->filled('table_name'), fn ($q) => $q->where('table_name', $request->string('table_name')->value()))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')->value()));

        $entries = $query->orderByDesc('id')->limit(200)->get();

        return response()->json(['data' => $entries]);
    }

    /**
     * @return array<string, mixed>
     */
    private function slabPayload(TaxSlab $slab): array
    {
        return [
            'id' => $slab->id,
            'company_id' => $slab->company_id,
            'country_code' => $slab->country_code,
            'name' => $slab->name,
            'min_amount' => $slab->min_amount,
            'max_amount' => $slab->max_amount,
            'rate' => $slab->rate,
            'fixed_deduction' => $slab->fixed_deduction,
            'effective_from' => $slab->effective_from->toDateString(),
            'effective_to' => $slab->effective_to?->toDateString(),
            'status' => $slab->status,
            'submitted_by' => $slab->submitted_by,
            'validated_by' => $slab->validated_by,
            'validated_at' => $slab->validated_at?->toIso8601String(),
            'rejection_reason' => $slab->rejection_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contributionPayload(SocialContribution $contribution): array
    {
        return [
            'id' => $contribution->id,
            'company_id' => $contribution->company_id,
            'country_code' => $contribution->country_code,
            'name' => $contribution->name,
            'code' => $contribution->code,
            'type' => $contribution->type,
            'rate' => $contribution->rate,
            'cap' => $contribution->cap,
            'effective_from' => $contribution->effective_from->toDateString(),
            'effective_to' => $contribution->effective_to?->toDateString(),
            'status' => $contribution->status,
            'submitted_by' => $contribution->submitted_by,
            'validated_by' => $contribution->validated_by,
            'validated_at' => $contribution->validated_at?->toIso8601String(),
            'rejection_reason' => $contribution->rejection_reason,
        ];
    }

    private function superAdmin(Request $request): SuperAdmin
    {
        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_api');

        return $superAdmin;
    }

    private function reason(Request $request): string
    {
        /** @var array{reason: string} $validated */
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        return $validated['reason'];
    }
}
