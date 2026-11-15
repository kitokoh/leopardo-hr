<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Domain\Models\AccountingFixedAsset;
use App\Modules\Accounting\Domain\Services\DepreciationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Contrôleur CRUD Immobilisations (#9377).
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
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('acquisition_date')
            ->paginate(50);

        return response()->json($assets);
    }

    /**
     * POST /api/v1/accounting/fixed-assets
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:200'],
            'asset_account_code' => ['required', 'string', 'max:20'],
            'depreciation_account_code' => ['required', 'string', 'max:20'],
            'expense_account_code' => ['required', 'string', 'max:20'],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'min:0'],
            'residual_value' => ['sometimes', 'numeric', 'min:0'],
            'useful_life_months' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(['linear'])], // declining pas encore implémenté
            'notes' => ['sometimes', 'string'],
        ]);

        $asset = AccountingFixedAsset::query()->create($validated);

        // Générer l'échéancier automatiquement
        $this->depreciationService->generateEntries($asset);

        $asset->load('depreciationEntries');

        return response()->json(['data' => $asset], 201);
    }

    /**
     * GET /api/v1/accounting/fixed-assets/{id}
     */
    public function show(int $id): JsonResponse
    {
        $asset = AccountingFixedAsset::query()
            ->with('depreciationEntries')
            ->findOrFail($id);

        return response()->json(['data' => $asset]);
    }

    /**
     * PUT /api/v1/accounting/fixed-assets/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $asset = AccountingFixedAsset::query()->findOrFail($id);

        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:200'],
            'notes' => ['sometimes', 'string'],
        ]);

        $asset->update($validated);

        return response()->json(['data' => $asset]);
    }

    /**
     * DELETE /api/v1/accounting/fixed-assets/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $asset = AccountingFixedAsset::query()->findOrFail($id);
        $asset->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /api/v1/accounting/fixed-assets/{id}/schedule
     * Échéancier d'amortissement.
     */
    public function schedule(int $id): JsonResponse
    {
        $asset = AccountingFixedAsset::query()->findOrFail($id);
        $schedule = $this->depreciationService->plan($asset);

        return response()->json(['data' => $schedule]);
    }
}
