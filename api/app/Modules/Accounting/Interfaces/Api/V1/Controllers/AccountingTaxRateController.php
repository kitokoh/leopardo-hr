<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Controllers;

use App\Modules\Accounting\Domain\Models\AccountingTaxRate;
use App\Modules\Accounting\Infrastructure\Services\TaxRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Référentiel de TVA du tenant (issue #9376).
 *
 * Le module déclarait le multi-taux par pays mais ne l'exposait nulle part :
 * `accounting_documents.tva_rate` était un taux unique, et
 * `accounting_document_lines.tax_id` un champ orphelin sans table. Ces routes
 * rendent le référentiel consultable et administrable.
 */
class AccountingTaxRateController extends Controller
{
    public function __construct(
        private readonly TaxRateService $taxRates,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $rates = AccountingTaxRate::query()
            ->where('company_id', $companyId)
            ->orderByDesc('is_default')
            ->orderBy('rate')
            ->get()
            ->map(static fn (AccountingTaxRate $rate): array => [
                'id' => $rate->id,
                'code' => $rate->code,
                'label' => $rate->label,
                'rate' => $rate->rate,
                'kind' => $rate->kind,
                'country' => $rate->country,
                'is_default' => $rate->is_default,
                'is_active' => $rate->is_active,
            ]);

        return response()->json(['data' => $rates]);
    }

    /**
     * Provisionne le référentiel depuis les défauts légaux du pays du tenant.
     * Idempotent : ne réécrit jamais un taux existant.
     */
    public function seed(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $company = $request->user()?->getAttribute('company');

        $province = is_object($company) && isset($company->metadata['province']) && is_string($company->metadata['province'])
            ? $company->metadata['province']
            : null;

        $created = $this->taxRates->seedForCompany(
            $companyId,
            is_string($company->country ?? null) ? $company->country : null,
            $province,
        );

        return response()->json(['created' => $created], 201);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'label' => ['required', 'string', 'max:120'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'kind' => ['nullable', 'string', 'in:standard,reduced,super_reduced,zero,exempt'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $companyId = $this->companyId($request);

        $rate = $this->taxRates->upsert($companyId, $validated);

        return response()->json([
            'id' => $rate->id,
            'code' => $rate->code,
            'label' => $rate->label,
            'rate' => $rate->rate,
            'is_default' => $rate->is_default,
            'is_active' => $rate->is_active,
        ], 201);
    }

    public function update(Request $request, AccountingTaxRate $taxRate): JsonResponse
    {
        $this->assertOwnedByTenant($request, $taxRate);

        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:120'],
            'rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'kind' => ['sometimes', 'string', 'in:standard,reduced,super_reduced,zero,exempt'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->taxRates->upsert($this->companyId($request), [
            'code' => $taxRate->code,
            'label' => $validated['label'] ?? $taxRate->label,
            'rate' => $validated['rate'] ?? $taxRate->rate,
            'kind' => $validated['kind'] ?? $taxRate->kind,
            'is_default' => $validated['is_default'] ?? $taxRate->is_default,
            'is_active' => $validated['is_active'] ?? $taxRate->is_active,
        ]);

        return response()->json([
            'id' => $updated->id,
            'code' => $updated->code,
            'label' => $updated->label,
            'rate' => $updated->rate,
            'is_default' => $updated->is_default,
            'is_active' => $updated->is_active,
        ]);
    }

    /**
     * Suppression refusée (409) si le taux est utilisé par une ligne de
     * document : l'historique des factures doit rester interprétable.
     */
    public function destroy(Request $request, AccountingTaxRate $taxRate): JsonResponse
    {
        $this->assertOwnedByTenant($request, $taxRate);

        if (! $this->taxRates->delete($this->companyId($request), $taxRate)) {
            return response()->json([
                'message' => __('accounting.errors.tax_rate_in_use'),
            ], 409);
        }

        return response()->json(null, 204);
    }

    private function assertOwnedByTenant(Request $request, AccountingTaxRate $taxRate): void
    {
        abort_if((string) $taxRate->company_id !== $this->companyId($request), 404);
    }

    /**
     * Compagnie de l'acteur authentifié — même pattern que les autres
     * contrôleurs du module (`getAttribute`, jamais la propriété directe :
     * `SuperAdmin` n'a pas de `company_id`).
     */
    private function companyId(Request $request): string
    {
        $companyId = $request->user()?->getAttribute('company_id');

        if (! is_string($companyId) || $companyId === '') {
            abort(403, 'Tenant context missing.');
        }

        return $companyId;
    }
}
