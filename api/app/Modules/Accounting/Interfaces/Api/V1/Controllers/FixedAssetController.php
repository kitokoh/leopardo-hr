<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Domain\Models\AccountingDepreciation;
use App\Modules\Accounting\Domain\Models\AccountingFixedAsset;
use App\Modules\Accounting\Infrastructure\Services\DepreciationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Contrôleur CRUD Immobilisations (#9377).
 *
 * Expose en HTTP le registre créé par #9378 : le modèle, la migration et le
 * service d'amortissement existaient déjà, mais **aucune route ne les
 * atteignait** — un tenant ne pouvait enregistrer une immobilisation qu'en
 * écrivant en base. Ce contrôleur est la seule couche ajoutée ; il ne
 * redéfinit ni le schéma ni le calcul.
 *
 * Le schéma de référence est celui de la migration
 * `2026_11_10_004000_9378_create_fixed_assets_tables.php` : `code`,
 * `in_service_date`, `disposed_on`, et la table de dotations
 * `accounting_depreciations`.
 */
class FixedAssetController extends Controller
{
    public function __construct(
        private readonly DepreciationService $depreciationService
    ) {}

    /**
     * GET /api/v1/accounting/fixed-assets
     */
    public function index(Request $request): JsonResponse
    {
        $assets = AccountingFixedAsset::query()
            ->when(
                $request->input('status'),
                fn ($q, $status) => $q->where('status', $status)
            )
            ->orderByDesc('in_service_date')
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json($assets);
    }

    /**
     * POST /api/v1/accounting/fixed-assets
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'label' => ['required', 'string', 'max:180'],
            'in_service_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'min:0'],
            'residual_value' => ['sometimes', 'numeric', 'min:0'],
            'useful_life_months' => ['required', 'integer', 'min:1'],
            // `declining` n'est pas implémenté : le service lève une exception
            // explicite. On refuse donc en amont plutôt que d'accepter puis
            // échouer en 500.
            'method' => ['required', Rule::in([AccountingFixedAsset::METHOD_LINEAR])],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['sometimes', 'string'],
        ]);

        $asset = AccountingFixedAsset::query()->create($validated);

        return response()->json(['data' => $asset], 201);
    }

    /**
     * GET /api/v1/accounting/fixed-assets/{id}
     */
    public function show(int $id): JsonResponse
    {
        $asset = AccountingFixedAsset::query()
            ->with('depreciations')
            ->findOrFail($id);

        return response()->json(['data' => $asset]);
    }

    /**
     * PUT /api/v1/accounting/fixed-assets/{id}
     *
     * Seuls le libellé et les notes sont modifiables : changer le coût, la
     * durée ou la date de mise en service après coup réécrirait l'historique
     * des dotations déjà comptabilisées.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $asset = AccountingFixedAsset::query()->findOrFail($id);

        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:180'],
            'notes' => ['sometimes', 'string'],
        ]);

        $asset->update($validated);

        return response()->json(['data' => $asset]);
    }

    /**
     * DELETE /api/v1/accounting/fixed-assets/{id}
     *
     * Refusé dès qu'une dotation existe : supprimer l'actif laisserait des
     * écritures comptables orphelines. La sortie du registre passe par
     * `status = disposed` et `disposed_on`.
     */
    public function destroy(int $id): JsonResponse
    {
        $asset = AccountingFixedAsset::query()->findOrFail($id);

        $hasDepreciations = AccountingDepreciation::query()
            ->where('fixed_asset_id', $asset->id)
            ->exists();

        if ($hasDepreciations) {
            return response()->json([
                'message' => __('accounting.errors.fixed_asset_has_depreciations'),
            ], 409);
        }

        $asset->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /api/v1/accounting/fixed-assets/{id}/schedule
     *
     * Échéancier prévisionnel — calculé, jamais comptabilisé.
     */
    public function schedule(int $id): JsonResponse
    {
        $asset = AccountingFixedAsset::query()->findOrFail($id);

        return response()->json(['data' => $this->depreciationService->schedule($asset)]);
    }
}
