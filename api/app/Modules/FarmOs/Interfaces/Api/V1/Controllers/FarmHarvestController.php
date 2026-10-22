<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Enums\FarmStorageLocationType;
use App\Modules\FarmOs\Domain\Enums\FarmStorageMovementType;
use App\Modules\FarmOs\Domain\Exceptions\FarmHarvestException;
use App\Modules\FarmOs\Domain\Models\FarmHarvestLot;
use App\Modules\FarmOs\Domain\Models\FarmStorageLocation;
use App\Modules\FarmOs\Domain\Models\FarmStorageMovement;
use App\Modules\FarmOs\Domain\Services\FarmCapabilityGate;
use App\Modules\FarmOs\Domain\Services\FarmHarvestService;
use App\Modules\FarmOs\Infrastructure\Context\FarmCompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Farm OS — T3.2 Recoltes, entreposage et tracabilite.
 *
 * Exigences d'honnetete portees par ce controleur :
 *   - l'outil `harvests` doit etre REELLEMENT active (fail-closed) ;
 *   - la quantite recoltee est FIGEE a la creation du lot : elle n'est jamais
 *     modifiable par l'API, sinon la tracabilite serait falsifiable ;
 *   - la quantite restante n'est jamais ecrite directement : elle decoule du
 *     journal des mouvements ;
 *   - une vente doit referencer un document du service de ventes EXISTANT :
 *     Farm OS ne cree aucun document commercial ;
 *   - le rapprochement recolte / stock / vente est expose et verifiable.
 */
final class FarmHarvestController
{
    public function __construct(
        private readonly FarmHarvestService $harvests,
        private readonly FarmCapabilityGate $gate,
    ) {}

    /**
     * Emplacements de stockage.
     */
    public function indexLocations(): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::HarvestStorage);

        $locations = FarmStorageLocation::query()
            ->where('company_id', $this->company()->id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $locations->map(fn (FarmStorageLocation $location): array => $this->locationPayload($location))->all(),
            'meta' => [
                'reference' => [
                    'location_types' => FarmStorageLocationType::values(),
                    'movement_types' => FarmStorageMovementType::values(),
                    'lot_statuses' => FarmHarvestLot::STATUSES,
                ],
            ],
        ]);
    }

    public function storeLocation(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::HarvestStorage);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:60'],
            'location_type' => ['required', 'string', Rule::in(FarmStorageLocationType::values())],
            'farm_site_id' => ['nullable', 'integer'],
            'capacity' => ['nullable', 'numeric', 'min:0'],
            'capacity_unit' => ['nullable', 'string', 'max:24'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_location_id' => ['nullable', 'string', 'max:80'],
        ]);

        $location = new FarmStorageLocation($validated);
        $location->company_id = $this->company()->id;
        $location->created_by = $request->user()?->id;
        $location->save();

        return response()->json(['data' => $this->locationPayload($location->refresh())], 201);
    }

    /**
     * Lots de recolte (tracabilite).
     */
    public function indexLots(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::HarvestStorage);

        $lots = FarmHarvestLot::query()
            ->when($request->filled('parcel_id'), fn ($query) => $query->where('parcel_id', $request->integer('parcel_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('crop_name'), fn ($query) => $query->where('crop_name', 'ilike', '%'.$request->string('crop_name')->toString().'%'))
            ->orderByDesc('harvested_at')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => $lots->getCollection()->map(fn (FarmHarvestLot $lot): array => $this->lotPayload($lot))->all(),
            'meta' => [
                'current_page' => $lots->currentPage(),
                'last_page' => $lots->lastPage(),
                'total' => $lots->total(),
            ],
        ]);
    }

    public function storeLot(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::HarvestStorage);

        $validated = $request->validate([
            'lot_code' => ['required', 'string', 'max:80'],
            'quantity_harvested' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:24'],
            'parcel_id' => ['nullable', 'integer'],
            'crop_cycle_id' => ['nullable', 'integer'],
            'crop_name' => ['nullable', 'string', 'max:160'],
            'quality_grade' => ['nullable', 'string', 'max:40'],
            'storage_location_id' => ['nullable', 'integer'],
            'farm_site_id' => ['nullable', 'integer'],
            'farm_operation_id' => ['nullable', 'integer'],
            'harvested_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_harvest_id' => ['nullable', 'string', 'max:80'],
        ]);

        return $this->run(fn (): FarmHarvestLot => $this->harvests->createLot(
            $this->company(),
            $validated['lot_code'],
            (string) $validated['quantity_harvested'],
            $validated['unit'],
            isset($validated['parcel_id']) ? (int) $validated['parcel_id'] : null,
            isset($validated['crop_cycle_id']) ? (int) $validated['crop_cycle_id'] : null,
            $validated['crop_name'] ?? null,
            $validated['quality_grade'] ?? null,
            isset($validated['storage_location_id']) ? (int) $validated['storage_location_id'] : null,
            isset($validated['farm_site_id']) ? (int) $validated['farm_site_id'] : null,
            isset($validated['farm_operation_id']) ? (int) $validated['farm_operation_id'] : null,
            $request->user()?->id,
            $validated['client_harvest_id'] ?? null,
            isset($validated['harvested_at']) ? Carbon::parse($validated['harvested_at']) : null,
        ), $this->lotPayload(...));
    }

    /**
     * Detail d'un lot : mouvements, rapprochement recolte / stock / vente.
     */
    public function showLot(FarmHarvestLot $lot): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::HarvestStorage);

        $lot->load(['movements' => fn ($query) => $query->orderByDesc('occurred_at')]);

        return response()->json([
            'data' => $this->lotPayload($lot) + [
                'movements' => $lot->movements->map(fn (FarmStorageMovement $movement): array => $this->movementPayload($movement))->all(),
                // Rapprochement verifiable : dispatched + remaining == harvested.
                'reconciliation' => $this->harvests->reconcile($this->company(), $lot),
            ],
        ]);
    }

    /**
     * Mouvement de stock (entree, transfert, perte, ajustement, vente,
     * consommation).
     */
    public function recordMovement(FarmHarvestLot $lot, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::HarvestStorage);

        $validated = $request->validate([
            'movement_type' => ['required', 'string', Rule::in(FarmStorageMovementType::values())],
            'quantity' => ['required', 'numeric', 'min:0'],
            'from_location_id' => ['nullable', 'integer'],
            'to_location_id' => ['nullable', 'integer'],
            // Reference vers le service de VENTES EXISTANT (obligatoire pour une
            // vente) : Farm OS ne cree aucun document commercial.
            'sale_document_id' => ['nullable', 'integer'],
            'sale_line_id' => ['nullable', 'integer'],
            'counterparty_id' => ['nullable', 'integer'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reason' => ['nullable', 'string', 'max:200'],
            'client_movement_id' => ['nullable', 'string', 'max:80'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        return $this->run(fn (): FarmStorageMovement => $this->harvests->recordMovement(
            $this->company(),
            $lot,
            FarmStorageMovementType::from($validated['movement_type']),
            (string) $validated['quantity'],
            isset($validated['from_location_id']) ? (int) $validated['from_location_id'] : null,
            isset($validated['to_location_id']) ? (int) $validated['to_location_id'] : null,
            isset($validated['sale_document_id']) ? (int) $validated['sale_document_id'] : null,
            isset($validated['sale_line_id']) ? (int) $validated['sale_line_id'] : null,
            isset($validated['counterparty_id']) ? (int) $validated['counterparty_id'] : null,
            isset($validated['unit_price']) ? (string) $validated['unit_price'] : null,
            $validated['currency'] ?? null,
            $validated['reason'] ?? null,
            $request->user()?->id,
            $validated['client_movement_id'] ?? null,
            isset($validated['occurred_at']) ? Carbon::parse($validated['occurred_at']) : null,
        ), $this->movementPayload(...));
    }

    /**
     * Stock restant par culture, pertes enregistrees.
     */
    public function summary(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::HarvestStorage);

        $from = $request->filled('from') ? Carbon::parse($request->string('from')->toString()) : null;
        $to = $request->filled('to') ? Carbon::parse($request->string('to')->toString()) : null;

        return response()->json([
            'data' => [
                'remaining_by_crop' => $this->harvests->remainingByCrop($this->company()),
                'losses' => $this->harvests->losses($this->company(), $from, $to),
            ],
        ]);
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  callable(): T  $action
     * @param  callable(T): array<string, mixed>  $payload
     */
    private function run(callable $action, callable $payload): JsonResponse
    {
        try {
            $result = $action();
        } catch (FarmHarvestException $exception) {
            return response()->json([
                'error' => $exception->errorCode(),
                'message' => $exception->errorCode(),
            ], 422);
        }

        return response()->json(['data' => $payload($result)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function locationPayload(FarmStorageLocation $location): array
    {
        return [
            'id' => $location->id,
            'farm_site_id' => $location->farm_site_id,
            'name' => $location->name,
            'reference' => $location->reference,
            'location_type' => $location->location_type,
            // Capacite inconnue = `null` : jamais estimee.
            'capacity' => $location->capacity,
            'capacity_unit' => $location->capacity_unit,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lotPayload(FarmHarvestLot $lot): array
    {
        return [
            'id' => $lot->id,
            'parcel_id' => $lot->parcel_id,
            'crop_cycle_id' => $lot->crop_cycle_id,
            'farm_site_id' => $lot->farm_site_id,
            'lot_code' => $lot->lot_code,
            'crop_name' => $lot->crop_name,
            // Quantite recoltee FIGEE : reference de tracabilite.
            'quantity_harvested' => $lot->quantity_harvested,
            'unit' => $lot->unit,
            'quality_grade' => $lot->quality_grade,
            'storage_location_id' => $lot->storage_location_id,
            'harvested_at' => $lot->harvested_at->toIso8601String(),
            // Cache derive du journal des mouvements.
            'quantity_remaining' => $lot->quantity_remaining,
            'status' => $lot->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function movementPayload(FarmStorageMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'farm_harvest_lot_id' => $movement->farm_harvest_lot_id,
            'movement_type' => $movement->movement_type,
            'quantity' => $movement->quantity,
            'quantity_after' => $movement->quantity_after,
            'from_location_id' => $movement->from_location_id,
            'to_location_id' => $movement->to_location_id,
            'sale_document_id' => $movement->sale_document_id,
            'sale_line_id' => $movement->sale_line_id,
            'counterparty_id' => $movement->counterparty_id,
            'unit_price' => $movement->unit_price,
            'currency' => $movement->currency,
            'reason' => $movement->reason,
            'occurred_at' => $movement->occurred_at->toIso8601String(),
        ];
    }

    private function company(): Company
    {
        return app(FarmCompanyContext::class)->current();
    }
}
