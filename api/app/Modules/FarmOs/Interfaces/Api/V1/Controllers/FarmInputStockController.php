<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Enums\FarmInputCategory;
use App\Modules\FarmOs\Domain\Enums\FarmInputUnit;
use App\Modules\FarmOs\Domain\Enums\FarmStockMovementType;
use App\Modules\FarmOs\Domain\Exceptions\FarmStockException;
use App\Modules\FarmOs\Domain\Models\FarmInput;
use App\Modules\FarmOs\Domain\Models\FarmInputLot;
use App\Modules\FarmOs\Domain\Models\FarmInputStockMovement;
use App\Modules\FarmOs\Domain\Services\FarmCapabilityGate;
use App\Modules\FarmOs\Domain\Services\FarmInputStockService;
use App\Modules\FarmOs\Infrastructure\Context\FarmCompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Farm OS — T2.1 Intrants et stocks agricoles.
 *
 * Exigences d'honnêteté portées par ce contrôleur :
 *   - l'outil `stocks_inputs` doit être RÉELLEMENT activé (fail-closed) ;
 *   - les quantités affichées sont celles du journal, jamais une estimation ;
 *   - un lot sans date de péremption n'est jamais présenté comme périmé ;
 *   - les produits réglementés sont signalés (`is_regulated`) mais Farm OS ne
 *     fournit AUCUN conseil de dosage ni de manipulation.
 */
final class FarmInputStockController
{
    public function __construct(
        private readonly FarmInputStockService $stock,
        private readonly FarmCapabilityGate $gate,
    ) {}

    /**
     * Catalogue d'intrants + quantité totale disponible par intrant.
     */
    public function index(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::StocksInputs);

        $inputs = FarmInput::query()
            ->with(['lots:id,farm_input_id,lot_number,expiry_date,quantity'])
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')->toString()))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'ilike', '%'.$request->string('search')->toString().'%'))
            ->orderBy('name')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => $inputs->getCollection()->map(fn (FarmInput $input): array => $this->inputPayload($input))->all(),
            'meta' => [
                'current_page' => $inputs->currentPage(),
                'last_page' => $inputs->lastPage(),
                'total' => $inputs->total(),
                'reference' => [
                    'categories' => FarmInputCategory::values(),
                    'units' => FarmInputUnit::values(),
                    'movement_types' => FarmStockMovementType::values(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::StocksInputs);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:60'],
            'category' => ['required', 'string', Rule::in(FarmInputCategory::values())],
            'unit' => ['required', 'string', Rule::in(FarmInputUnit::values())],
            'packaging' => ['nullable', 'string', 'max:80'],
            'alert_threshold' => ['nullable', 'numeric', 'min:0'],
            'is_regulated' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $category = FarmInputCategory::from($validated['category']);

        $input = new FarmInput($validated);
        // Un produit réglementé est signalé par défaut selon sa catégorie :
        // l'exploitant peut le corriger, mais on ne masque jamais l'information.
        $input->is_regulated = $validated['is_regulated'] ?? $category->isRegulatedByDefault();
        $input->company_id = $this->company()->id;
        $input->save();

        return response()->json(['data' => $this->inputPayload($input->refresh())], 201);
    }

    public function show(FarmInput $input): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::StocksInputs);

        $input->load(['lots:id,farm_input_id,lot_number,expiry_date,quantity']);

        return response()->json([
            'data' => $this->inputPayload($input) + [
                'lots' => $input->lots->map(fn (FarmInputLot $lot): array => $this->lotPayload($lot))->all(),
            ],
        ]);
    }

    /**
     * Journal des mouvements d'un intrant (lecture seule : le journal est
     * immuable, on n'y écrit que par les actions de stock).
     */
    public function movements(FarmInput $input, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::StocksInputs);

        $movements = FarmInputStockMovement::query()
            ->where('farm_input_id', $input->id)
            ->when($request->filled('movement_type'), fn ($query) => $query->where('movement_type', $request->string('movement_type')->toString()))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 50));

        return response()->json([
            'data' => $movements->getCollection()->map(fn (FarmInputStockMovement $movement): array => $this->movementPayload($movement))->all(),
            'meta' => ['current_page' => $movements->currentPage(), 'last_page' => $movements->lastPage(), 'total' => $movements->total()],
        ]);
    }

    /**
     * Réception d'un lot (entrée de stock).
     */
    public function receive(FarmInput $input, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::StocksInputs);

        $validated = $request->validate([
            'lot_number' => ['required', 'string', 'max:80'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'expiry_date' => ['nullable', 'date'],
            'supplier_id' => ['nullable', 'integer'],
            'client_movement_id' => ['nullable', 'string', 'max:80'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        return $this->run(fn (): FarmInputStockMovement => $this->stock->receive(
            $this->company(),
            $input,
            $validated['lot_number'],
            (string) $validated['quantity'],
            $validated['expiry_date'] ?? null,
            isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
            $request->user()?->id,
            $validated['client_movement_id'] ?? null,
            isset($validated['occurred_at']) ? Carbon::parse($validated['occurred_at']) : null,
        ));
    }

    /**
     * Sortie de stock (consommation sur une opération, vente, perte).
     */
    public function issue(FarmInput $input, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::StocksInputs);

        $validated = $request->validate([
            'lot_number' => ['required', 'string', 'max:80'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'farm_operation_id' => ['nullable', 'integer'],
            'parcel_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:200'],
            'client_movement_id' => ['nullable', 'string', 'max:80'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        return $this->run(fn (): FarmInputStockMovement => $this->stock->issue(
            $this->company(),
            $input,
            $validated['lot_number'],
            (string) $validated['quantity'],
            isset($validated['farm_operation_id']) ? (int) $validated['farm_operation_id'] : null,
            isset($validated['parcel_id']) ? (int) $validated['parcel_id'] : null,
            $validated['reason'] ?? null,
            $request->user()?->id,
            $validated['client_movement_id'] ?? null,
            isset($validated['occurred_at']) ? Carbon::parse($validated['occurred_at']) : null,
        ));
    }

    /**
     * Ajustement d'inventaire : la quantité envoyée est la quantité CIBLE.
     */
    public function adjust(FarmInput $input, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::StocksInputs);

        $validated = $request->validate([
            'lot_number' => ['required', 'string', 'max:80'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:200'],
            'client_movement_id' => ['nullable', 'string', 'max:80'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        return $this->run(fn (): FarmInputStockMovement => $this->stock->adjust(
            $this->company(),
            $input,
            $validated['lot_number'],
            (string) $validated['quantity'],
            $validated['reason'] ?? null,
            $request->user()?->id,
            $validated['client_movement_id'] ?? null,
            isset($validated['occurred_at']) ? Carbon::parse($validated['occurred_at']) : null,
        ));
    }

    /**
     * Intrants sous le seuil d'alerte (ou en rupture). Un intrant SANS seuil
     * n'apparaît jamais ici : Farm OS n'invente pas de seuil par défaut.
     */
    public function alerts(): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::StocksInputs);

        $inputs = FarmInput::query()
            ->with(['lots:id,farm_input_id,lot_number,expiry_date,quantity'])
            ->whereNotNull('alert_threshold')
            ->orderBy('name')
            ->get();

        $alerts = [];

        foreach ($inputs as $input) {
            $available = $this->availableQuantity($input);
            $threshold = (float) $input->alert_threshold;

            if ($available > $threshold) {
                continue;
            }

            $alerts[] = [
                'farm_input_id' => $input->id,
                'name' => $input->name,
                'unit' => $input->unit,
                'available' => number_format($available, 3, '.', ''),
                'alert_threshold' => number_format($threshold, 3, '.', ''),
                // Rupture = zéro disponible. On ne confond pas « bas » et « vide ».
                'is_out_of_stock' => $available <= 0.0,
            ];
        }

        return response()->json(['data' => $alerts]);
    }

    /**
     * Exécute une action de stock et traduit un refus métier en 422 explicite
     * (jamais un 500, jamais un succès trompeur).
     *
     * @param  callable(): FarmInputStockMovement  $action
     */
    private function run(callable $action): JsonResponse
    {
        try {
            $movement = $action();
        } catch (FarmStockException $exception) {
            return response()->json([
                'error' => $exception->errorCode(),
                'message' => $exception->errorCode(),
            ], 422);
        }

        return response()->json(['data' => $this->movementPayload($movement)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function inputPayload(FarmInput $input): array
    {
        $available = $this->availableQuantity($input);
        $threshold = $input->alert_threshold !== null ? (float) $input->alert_threshold : null;

        return [
            'id' => $input->id,
            'name' => $input->name,
            'reference' => $input->reference,
            'category' => $input->category,
            'unit' => $input->unit,
            'packaging' => $input->packaging,
            'is_regulated' => (bool) $input->is_regulated,
            'notes' => $input->notes,
            'available_quantity' => number_format($available, 3, '.', ''),
            'alert_threshold' => $threshold !== null ? number_format($threshold, 3, '.', '') : null,
            // Honnêteté : `null` quand aucun seuil n'est défini — jamais `false`
            // (qui laisserait croire qu'un seuil existe et n'est pas atteint).
            'is_below_threshold' => $threshold !== null ? $available <= $threshold : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lotPayload(FarmInputLot $lot): array
    {
        return [
            'id' => $lot->id,
            'lot_number' => $lot->lot_number,
            'expiry_date' => $lot->expiry_date?->toDateString(),
            'quantity' => $lot->quantity,
            // Un lot sans date de péremption n'est jamais « périmé ».
            'is_expired' => $lot->isExpiredAt(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function movementPayload(FarmInputStockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'farm_input_id' => $movement->farm_input_id,
            'farm_input_lot_id' => $movement->farm_input_lot_id,
            'movement_type' => $movement->movement_type,
            'quantity' => $movement->quantity,
            'quantity_after' => $movement->quantity_after,
            'farm_operation_id' => $movement->farm_operation_id,
            'parcel_id' => $movement->parcel_id,
            'reason' => $movement->reason,
            'occurred_at' => $movement->occurred_at->toIso8601String(),
        ];
    }

    /**
     * Quantité disponible = somme des lots. Aucune conversion d'unité n'est
     * faite : tous les lots d'un intrant partagent son unité de gestion.
     */
    private function availableQuantity(FarmInput $input): float
    {
        $total = 0.0;

        foreach ($input->lots as $lot) {
            $total += (float) $lot->quantity;
        }

        return $total;
    }

    private function company(): Company
    {
        return app(FarmCompanyContext::class)->current();
    }
}
