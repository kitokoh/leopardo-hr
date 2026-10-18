<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmBreakdownSeverity;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Enums\FarmEquipmentKind;
use App\Modules\FarmOs\Domain\Enums\FarmEquipmentMeterType;
use App\Modules\FarmOs\Domain\Enums\FarmEquipmentStatus;
use App\Modules\FarmOs\Domain\Enums\FarmMaintenanceStatus;
use App\Modules\FarmOs\Domain\Enums\FarmMaintenanceType;
use App\Modules\FarmOs\Domain\Enums\FarmMeterSource;
use App\Modules\FarmOs\Domain\Exceptions\FarmEquipmentException;
use App\Modules\FarmOs\Domain\Models\FarmEquipment;
use App\Modules\FarmOs\Domain\Models\FarmEquipmentBreakdown;
use App\Modules\FarmOs\Domain\Models\FarmEquipmentDocument;
use App\Modules\FarmOs\Domain\Models\FarmEquipmentMaintenance;
use App\Modules\FarmOs\Domain\Models\FarmEquipmentUsageLog;
use App\Modules\FarmOs\Domain\Services\FarmCapabilityGate;
use App\Modules\FarmOs\Domain\Services\FarmEquipmentService;
use App\Modules\FarmOs\Infrastructure\Context\FarmCompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Farm OS — T2.2 Materiel agricole.
 *
 * Exigences d'honnetete portees par ce controleur :
 *   - l'outil `equipment` doit etre REELLEMENT active (fail-closed) ;
 *   - les compteurs affiches sont ceux SAISIS ou IMPORTES : `meter_source` ne
 *     peut valoir que `manual` / `imported`, jamais `sensor` — Farm OS n'a
 *     aucune telemetrie integree et ne pretend donc jamais connaitre
 *     automatiquement les heures moteur, la position GPS ou l'etat mecanique ;
 *   - un materiel sans compteur renvoie `meter_value: null` (jamais 0) ;
 *   - un cout inconnu reste `null` et le total est declare INCOMPLET.
 */
final class FarmEquipmentController
{
    public function __construct(
        private readonly FarmEquipmentService $equipment,
        private readonly FarmCapabilityGate $gate,
    ) {}

    /**
     * Registre du materiel.
     */
    public function index(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Equipment);

        $equipment = FarmEquipment::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('kind'), fn ($query) => $query->where('kind', $request->string('kind')->toString()))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'ilike', '%'.$request->string('search')->toString().'%'))
            ->orderBy('name')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => $equipment->getCollection()->map(fn (FarmEquipment $item): array => $this->equipmentPayload($item))->all(),
            'meta' => [
                'current_page' => $equipment->currentPage(),
                'last_page' => $equipment->lastPage(),
                'total' => $equipment->total(),
                'reference' => [
                    'kinds' => FarmEquipmentKind::values(),
                    'statuses' => FarmEquipmentStatus::values(),
                    'meter_types' => FarmEquipmentMeterType::values(),
                    'meter_sources' => FarmMeterSource::values(),
                    'maintenance_types' => FarmMaintenanceType::values(),
                    'maintenance_statuses' => FarmMaintenanceStatus::values(),
                    'breakdown_severities' => FarmBreakdownSeverity::values(),
                    'document_types' => FarmEquipmentDocument::TYPES,
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Equipment);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:60'],
            'kind' => ['required', 'string', Rule::in(FarmEquipmentKind::values())],
            'brand' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'serial_number' => ['nullable', 'string', 'max:80'],
            'plate_number' => ['nullable', 'string', 'max:32'],
            'farm_site_id' => ['nullable', 'integer'],
            'acquired_on' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'meter_type' => ['nullable', 'string', Rule::in(FarmEquipmentMeterType::values())],
            'meter_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(FarmEquipmentStatus::values())],
            'assigned_team_id' => ['nullable', 'integer'],
            'assigned_employee_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_equipment_id' => ['nullable', 'string', 'max:80'],
        ]);

        $kind = FarmEquipmentKind::from($validated['kind']);
        // Type de compteur par defaut PROPOSE selon le type de materiel, mais
        // toujours surchargeable : Farm OS ne devine jamais un compteur reel.
        $meterType = isset($validated['meter_type'])
            ? FarmEquipmentMeterType::from($validated['meter_type'])
            : $kind->defaultMeterType();

        $equipment = new FarmEquipment($validated);
        $equipment->meter_type = $meterType->value;
        // Un materiel sans compteur n'a AUCUNE valeur de compteur : on ne stocke
        // pas un zero trompeur.
        $equipment->meter_value = $meterType->isTracked() && isset($validated['meter_value'])
            ? number_format((float) $validated['meter_value'], 2, '.', '')
            : null;
        $equipment->meter_source = $equipment->meter_value !== null ? FarmMeterSource::Manual->value : null;
        $equipment->status = $validated['status'] ?? FarmEquipmentStatus::Active->value;
        $equipment->company_id = $this->company()->id;
        $equipment->created_by = $request->user()?->id;
        $equipment->save();

        return response()->json(['data' => $this->equipmentPayload($equipment->refresh())], 201);
    }

    public function show(FarmEquipment $equipment): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Equipment);

        $equipment->load([
            'usageLogs' => fn ($query) => $query->orderByDesc('occurred_at')->limit(50),
            'maintenances' => fn ($query) => $query->orderByDesc('created_at')->limit(50),
            'breakdowns' => fn ($query) => $query->orderByDesc('occurred_at')->limit(50),
            'documents',
        ]);

        return response()->json([
            'data' => $this->equipmentPayload($equipment) + [
                'usage_logs' => $equipment->usageLogs->map(fn (FarmEquipmentUsageLog $log): array => $this->usagePayload($log))->all(),
                'maintenances' => $equipment->maintenances->map(fn (FarmEquipmentMaintenance $m): array => $this->maintenancePayload($m))->all(),
                'breakdowns' => $equipment->breakdowns->map(fn (FarmEquipmentBreakdown $b): array => $this->breakdownPayload($b))->all(),
                'documents' => $equipment->documents->map(fn (FarmEquipmentDocument $d): array => $this->documentPayload($d))->all(),
                'costs' => $this->equipment->knownCosts($this->company(), $equipment),
            ],
        ]);
    }

    /**
     * Releve de compteur (saisie manuelle ou import) — jamais une lecture
     * automatique.
     */
    public function recordUsage(FarmEquipment $equipment, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Equipment);

        $validated = $request->validate([
            'meter_value' => ['required', 'numeric', 'min:0'],
            'meter_source' => ['nullable', 'string', Rule::in(FarmMeterSource::values())],
            'farm_operation_id' => ['nullable', 'integer'],
            'parcel_id' => ['nullable', 'integer'],
            'operator_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:200'],
            'client_usage_id' => ['nullable', 'string', 'max:80'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        return $this->run(fn (): FarmEquipmentUsageLog => $this->equipment->recordUsage(
            $this->company(),
            $equipment,
            (string) $validated['meter_value'],
            isset($validated['meter_source']) ? FarmMeterSource::from($validated['meter_source']) : null,
            isset($validated['farm_operation_id']) ? (int) $validated['farm_operation_id'] : null,
            isset($validated['parcel_id']) ? (int) $validated['parcel_id'] : null,
            isset($validated['operator_id']) ? (int) $validated['operator_id'] : null,
            $validated['reason'] ?? null,
            $request->user()?->id,
            $validated['client_usage_id'] ?? null,
            isset($validated['occurred_at']) ? Carbon::parse($validated['occurred_at']) : null,
        ), $this->usagePayload(...));
    }

    /**
     * Remplacement explicite du compteur (changement de compteur, correction).
     */
    public function resetMeter(FarmEquipment $equipment, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Equipment);

        $validated = $request->validate([
            'meter_value' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:200'],
            'client_usage_id' => ['nullable', 'string', 'max:80'],
        ]);

        return $this->run(fn (): FarmEquipmentUsageLog => $this->equipment->resetMeter(
            $this->company(),
            $equipment,
            (string) $validated['meter_value'],
            $validated['reason'],
            $request->user()?->id,
            $validated['client_usage_id'] ?? null,
        ), $this->usagePayload(...));
    }

    /**
     * Planification d'un entretien.
     */
    public function storeMaintenance(FarmEquipment $equipment, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Equipment);

        $validated = $request->validate([
            'maintenance_type' => ['required', 'string', Rule::in(FarmMaintenanceType::values())],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'planned_at' => ['nullable', 'date'],
            'meter_value_at' => ['nullable', 'numeric', 'min:0'],
            'cost_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'supplier_id' => ['nullable', 'integer'],
            'breakdown_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_maintenance_id' => ['nullable', 'string', 'max:80'],
        ]);

        $maintenance = new FarmEquipmentMaintenance($validated);
        $maintenance->farm_equipment_id = $equipment->id;
        $maintenance->status = FarmMaintenanceStatus::Planned->value;
        $maintenance->company_id = $this->company()->id;
        $maintenance->recorded_by = $request->user()?->id;
        $maintenance->save();

        return response()->json(['data' => $this->maintenancePayload($maintenance->refresh())], 201);
    }

    /**
     * Cloture d'un entretien (il devient `done`, non rouvrable).
     */
    public function completeMaintenance(FarmEquipmentMaintenance $maintenance, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Equipment);

        $validated = $request->validate([
            'cost_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'performed_at' => ['nullable', 'date'],
            'meter_value_at' => ['nullable', 'numeric', 'min:0'],
        ]);

        return $this->run(fn (): FarmEquipmentMaintenance => $this->equipment->completeMaintenance(
            $this->company(),
            $maintenance,
            isset($validated['cost_amount']) ? (string) $validated['cost_amount'] : null,
            $validated['currency'] ?? null,
            isset($validated['performed_at']) ? Carbon::parse($validated['performed_at']) : null,
            isset($validated['meter_value_at']) ? (string) $validated['meter_value_at'] : null,
        ), $this->maintenancePayload(...));
    }

    /**
     * Declaration d'une panne (gravite DECLAREE, aucun diagnostic automatise).
     */
    public function storeBreakdown(FarmEquipment $equipment, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Equipment);

        $validated = $request->validate([
            'severity' => ['required', 'string', Rule::in(FarmBreakdownSeverity::values())],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['required', 'date'],
            'downtime_hours' => ['nullable', 'numeric', 'min:0'],
            'cost_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_breakdown_id' => ['nullable', 'string', 'max:80'],
        ]);

        $breakdown = new FarmEquipmentBreakdown($validated);
        $breakdown->farm_equipment_id = $equipment->id;
        $breakdown->company_id = $this->company()->id;
        $breakdown->reported_by = $request->user()?->id;
        $breakdown->save();

        return response()->json(['data' => $this->breakdownPayload($breakdown->refresh())], 201);
    }

    /**
     * Resolution d'une panne (idempotent : une panne deja resolue est renvoyee
     * telle quelle, sa date de resolution n'est jamais reecrite).
     */
    public function resolveBreakdown(FarmEquipmentBreakdown $breakdown, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Equipment);

        $validated = $request->validate([
            'cost_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'downtime_hours' => ['nullable', 'numeric', 'min:0'],
            'resolved_at' => ['nullable', 'date'],
        ]);

        return $this->run(fn (): FarmEquipmentBreakdown => $this->equipment->resolveBreakdown(
            $this->company(),
            $breakdown,
            isset($validated['cost_amount']) ? (string) $validated['cost_amount'] : null,
            $validated['currency'] ?? null,
            isset($validated['downtime_hours']) ? (string) $validated['downtime_hours'] : null,
            isset($validated['resolved_at']) ? Carbon::parse($validated['resolved_at']) : null,
        ), $this->breakdownPayload(...));
    }

    /**
     * Document associe (reference au service de fichiers existant, ou lien
     * fourni par l'exploitant — jamais un lien fabrique par Farm OS).
     */
    public function storeDocument(FarmEquipment $equipment, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Equipment);

        $validated = $request->validate([
            'document_type' => ['required', 'string', Rule::in(FarmEquipmentDocument::TYPES)],
            'title' => ['required', 'string', 'max:160'],
            'file_id' => ['nullable', 'integer'],
            'external_url' => ['nullable', 'url', 'max:500'],
            'issued_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $document = new FarmEquipmentDocument($validated);
        $document->farm_equipment_id = $equipment->id;
        $document->company_id = $this->company()->id;
        $document->created_by = $request->user()?->id;
        $document->save();

        return response()->json(['data' => $this->documentPayload($document->refresh())], 201);
    }

    /**
     * Materiel necessitant une intervention : entretiens echus + pannes non
     * resolues. Aucune prediction mecanique — uniquement des echeances
     * DECLAREES.
     */
    public function maintenanceDue(): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Equipment);

        return response()->json(['data' => $this->equipment->maintenanceDue($this->company())]);
    }

    /**
     * Execute une action metier et traduit un refus en 422 explicite (jamais un
     * 500, jamais un succes trompeur).
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     * @param  callable(): T  $action
     * @param  callable(T): array<string, mixed>  $payload
     */
    private function run(callable $action, callable $payload): JsonResponse
    {
        try {
            $result = $action();
        } catch (FarmEquipmentException $exception) {
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
    private function equipmentPayload(FarmEquipment $equipment): array
    {
        $tracksMeter = $equipment->tracksMeter();

        return [
            'id' => $equipment->id,
            'farm_site_id' => $equipment->farm_site_id,
            'name' => $equipment->name,
            'reference' => $equipment->reference,
            'kind' => $equipment->kind,
            'brand' => $equipment->brand,
            'model' => $equipment->model,
            'serial_number' => $equipment->serial_number,
            'plate_number' => $equipment->plate_number,
            'acquired_on' => $equipment->acquired_on?->toDateString(),
            'acquisition_cost' => $equipment->acquisition_cost,
            'currency' => $equipment->currency,
            'meter_type' => $equipment->meter_type,
            // Honnetete : un materiel sans compteur renvoie `null`, jamais 0.
            'meter_value' => $tracksMeter ? $equipment->meter_value : null,
            'meter_source' => $tracksMeter ? $equipment->meter_source : null,
            'tracks_meter' => $tracksMeter,
            'status' => $equipment->status,
            'assigned_team_id' => $equipment->assigned_team_id,
            'assigned_employee_id' => $equipment->assigned_employee_id,
            'notes' => $equipment->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function usagePayload(FarmEquipmentUsageLog $log): array
    {
        return [
            'id' => $log->id,
            'farm_equipment_id' => $log->farm_equipment_id,
            'meter_value' => $log->meter_value,
            'meter_delta' => $log->meter_delta,
            'meter_source' => $log->meter_source,
            'farm_operation_id' => $log->farm_operation_id,
            'parcel_id' => $log->parcel_id,
            'operator_id' => $log->operator_id,
            'reason' => $log->reason,
            'occurred_at' => $log->occurred_at->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function maintenancePayload(FarmEquipmentMaintenance $maintenance): array
    {
        return [
            'id' => $maintenance->id,
            'farm_equipment_id' => $maintenance->farm_equipment_id,
            'maintenance_type' => $maintenance->maintenance_type,
            'status' => $maintenance->status,
            'title' => $maintenance->title,
            'description' => $maintenance->description,
            'planned_at' => $maintenance->planned_at?->toIso8601String(),
            'performed_at' => $maintenance->performed_at?->toIso8601String(),
            'meter_value_at' => $maintenance->meter_value_at,
            // Un cout inconnu reste `null` : jamais remplace par zero.
            'cost_amount' => $maintenance->cost_amount,
            'currency' => $maintenance->currency,
            'supplier_id' => $maintenance->supplier_id,
            'breakdown_id' => $maintenance->breakdown_id,
            'is_open' => $maintenance->isOpen(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function breakdownPayload(FarmEquipmentBreakdown $breakdown): array
    {
        return [
            'id' => $breakdown->id,
            'farm_equipment_id' => $breakdown->farm_equipment_id,
            'severity' => $breakdown->severity,
            'title' => $breakdown->title,
            'description' => $breakdown->description,
            'occurred_at' => $breakdown->occurred_at->toIso8601String(),
            'resolved_at' => $breakdown->resolved_at?->toIso8601String(),
            'downtime_hours' => $breakdown->downtime_hours,
            'cost_amount' => $breakdown->cost_amount,
            'currency' => $breakdown->currency,
            'is_resolved' => $breakdown->isResolved(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPayload(FarmEquipmentDocument $document): array
    {
        return [
            'id' => $document->id,
            'farm_equipment_id' => $document->farm_equipment_id,
            'document_type' => $document->document_type,
            'title' => $document->title,
            'file_id' => $document->file_id,
            'external_url' => $document->external_url,
            'issued_on' => $document->issued_on?->toDateString(),
            'expires_on' => $document->expires_on?->toDateString(),
            // Un document sans echeance n'est jamais « expire ».
            'is_expired' => $document->isExpiredAt(),
        ];
    }

    private function company(): Company
    {
        return app(FarmCompanyContext::class)->current();
    }
}
