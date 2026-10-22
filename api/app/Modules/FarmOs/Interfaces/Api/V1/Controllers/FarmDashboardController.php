<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Enums\FarmOperationStatus;
use App\Modules\FarmOs\Domain\Models\CropCycle;
use App\Modules\FarmOs\Domain\Models\FarmAnimalHealthEvent;
use App\Modules\FarmOs\Domain\Models\FarmEquipment;
use App\Modules\FarmOs\Domain\Models\FarmEquipmentMaintenance;
use App\Modules\FarmOs\Domain\Models\FarmHarvestLot;
use App\Modules\FarmOs\Domain\Models\FarmInput;
use App\Modules\FarmOs\Domain\Models\FarmLivestockBatch;
use App\Modules\FarmOs\Domain\Models\FarmOperation;
use App\Modules\FarmOs\Domain\Models\Parcel;
use App\Modules\FarmOs\Domain\Services\FarmCapabilityGate;
use App\Modules\FarmOs\Domain\Services\FarmProfitabilityService;
use App\Modules\FarmOs\Domain\Services\FarmSyncService;
use App\Modules\FarmOs\Infrastructure\Context\FarmCompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Farm OS — Tableau de bord de l'exploitation.
 *
 * Principe : les sections affichées dépendent des CAPACITÉS réellement
 * activées ; chaque section porte un état honnête (donnée disponible, vide
 * utile, ou non calculable faute de données). Farm OS ne fabrique aucune
 * donnée manquante — un indicateur sans matière à calcul est déclaré
 * `insufficient_data` plutôt qu'affiché à zéro.
 */
final class FarmDashboardController
{
    public function __construct(
        private readonly FarmCapabilityGate $gate,
        private readonly FarmSyncService $sync,
        private readonly FarmProfitabilityService $profitability,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $company = $this->company();
        $enabled = $this->gate->enabled($company);
        $now = Carbon::now();

        $sections = [];

        if (in_array(FarmCapability::WorkPlanning->value, $enabled, true)) {
            $sections['today_operations'] = $this->todayOperations($request);
            $sections['late_operations'] = $this->lateOperations($request);
        }

        if (in_array(FarmCapability::ParcelsCrops->value, $enabled, true)) {
            $sections['parcels'] = [
                'total' => Parcel::query()->count(),
                'active' => Parcel::query()->where('status', 'active')->count(),
                'with_geometry' => Parcel::query()->whereNotNull('geometry_wkt')->count(),
            ];

            $sections['crop_cycles'] = [
                'growing' => CropCycle::query()->where('status', 'growing')->count(),
                'planned' => CropCycle::query()->where('status', 'planned')->count(),
            ];
        }

        // Sections des modules LIVRES : chacune n'apparait que si la capacite
        // correspondante est activee, et chacune porte un etat honnete (donnee
        // disponible, vide utile, ou non calculable faute de donnees).
        if (in_array(FarmCapability::StocksInputs->value, $enabled, true)) {
            $sections['inputs_to_reorder'] = $this->inputsToReorder($company);
        }

        if (in_array(FarmCapability::Equipment->value, $enabled, true)) {
            $sections['equipment_maintenance_due'] = $this->equipmentMaintenanceDue($company, $now);
        }

        if (in_array(FarmCapability::Livestock->value, $enabled, true)) {
            $sections['livestock'] = $this->livestock($company, $now);
        }

        if (in_array(FarmCapability::HarvestStorage->value, $enabled, true)) {
            $sections['harvest_stock'] = $this->harvestStock($company);
        }

        if (in_array(FarmCapability::FinanceExpenses->value, $enabled, true)) {
            $sections['finance'] = $this->finance($company);
        }

        // Operations en attente de synchronisation : visible uniquement si le
        // mode hors ligne a reellement quelque chose a remonter.
        $sections['pending_sync'] = $this->pendingSync($company);

        return response()->json([
            'data' => [
                'generated_at' => $now->toIso8601String(),
                'enabled_capabilities' => $enabled,
                'sections' => $sections,
                'empty_state' => $this->emptyState(),
            ],
        ]);
    }

    /**
     * Intrants sous leur seuil d'alerte. Un intrant SANS seuil declare n'y
     * apparait jamais : Farm OS n'invente pas de seuil.
     *
     * @return array<string, mixed>
     */
    private function inputsToReorder(Company $company): array
    {
        $rows = [];

        $inputs = FarmInput::query()
            ->where('company_id', $company->id)
            ->whereNotNull('alert_threshold')
            ->get();

        foreach ($inputs as $input) {
            $quantity = $this->inputQuantity($company, (int) $input->id);

            if ((float) $quantity < (float) $input->alert_threshold) {
                $rows[] = [
                    'input_id' => (int) $input->id,
                    'name' => (string) $input->name,
                    'quantity' => $quantity,
                    'threshold' => (string) $input->alert_threshold,
                    'unit' => (string) $input->unit,
                ];
            }
        }

        return [
            'count' => count($rows),
            'items' => $rows,
            'state' => $rows === [] ? 'nothing_below_threshold' : 'data_available',
        ];
    }

    private function inputQuantity(Company $company, int $inputId): string
    {
        $connection = (new FarmInput)->getConnection();
        $table = (new FarmInput)->getTable();

        $value = $connection->table($table)
            ->where('company_id', $company->id)
            ->where('id', $inputId)
            ->value('quantity_on_hand');

        return is_numeric($value) ? number_format((float) $value, 3, '.', '') : '0.000';
    }

    /**
     * @return array<string, mixed>
     */
    private function equipmentMaintenanceDue(Company $company, Carbon $now): array
    {
        $rows = [];

        $maintenances = FarmEquipmentMaintenance::query()
            ->where('company_id', $company->id)
            ->where('status', 'planned')
            ->whereNotNull('planned_at')
            ->where('planned_at', '<=', $now)
            ->orderBy('planned_at')
            ->limit(10)
            ->get();

        foreach ($maintenances as $maintenance) {
            $equipment = FarmEquipment::query()
                ->where('company_id', $company->id)
                ->where('id', $maintenance->farm_equipment_id)
                ->first();

            $rows[] = [
                'maintenance_id' => (int) $maintenance->id,
                'equipment_id' => (int) $maintenance->farm_equipment_id,
                'equipment_name' => $equipment?->name,
                'title' => (string) $maintenance->title,
                'planned_at' => $maintenance->planned_at?->toIso8601String(),
            ];
        }

        return [
            'count' => count($rows),
            'items' => $rows,
            'state' => $rows === [] ? 'nothing_due' : 'data_available',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function livestock(Company $company, Carbon $now): array
    {
        $batches = FarmLivestockBatch::query()->where('company_id', $company->id)->get();
        $present = 0;

        foreach ($batches as $batch) {
            $present += (int) $batch->head_count;
        }

        $reminders = FarmAnimalHealthEvent::query()
            ->where('company_id', $company->id)
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<=', $now)
            ->orderBy('next_due_at')
            ->limit(10)
            ->get();

        $items = [];

        foreach ($reminders as $reminder) {
            $items[] = [
                'title' => (string) $reminder->title,
                'event_type' => (string) $reminder->event_type,
                'next_due_at' => $reminder->next_due_at?->toIso8601String(),
            ];
        }

        return [
            'batches' => $batches->count(),
            'present_head' => $present,
            'health_reminders_due' => count($items),
            'items' => $items,
            'state' => $batches->count() === 0 ? 'no_herd_recorded' : 'data_available',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function harvestStock(Company $company): array
    {
        $lots = FarmHarvestLot::query()
            ->where('company_id', $company->id)
            ->where('status', 'open')
            ->get();

        $byCrop = [];

        foreach ($lots as $lot) {
            $crop = (string) ($lot->crop_name ?? 'unknown');
            $unit = (string) $lot->unit;
            $key = $crop.'|'.$unit;

            if (! isset($byCrop[$key])) {
                $byCrop[$key] = ['crop' => $crop, 'remaining' => 0.0, 'unit' => $unit];
            }

            $byCrop[$key]['remaining'] += (float) $lot->quantity_remaining;
        }

        $rows = [];

        foreach ($byCrop as $row) {
            $rows[] = [
                'crop' => $row['crop'],
                'remaining' => number_format($row['remaining'], 3, '.', ''),
                'unit' => $row['unit'],
            ];
        }

        return [
            'open_lots' => $lots->count(),
            'remaining_by_crop' => $rows,
            'state' => $lots->count() === 0 ? 'no_harvest_recorded' : 'data_available',
        ];
    }

    /**
     * Ventes et depenses ENREGISTREES, avec la fiabilite explicite : une marge
     * calculee sur des donnees incompletes n'est jamais presentee comme certaine.
     *
     * @return array<string, mixed>
     */
    private function finance(Company $company): array
    {
        $result = $this->profitability->profitability($company, \App\Modules\FarmOs\Domain\Enums\FarmCostAxis::Farm);

        return [
            'costs_total' => $result['costs_total'],
            'costs_real' => $result['costs_real'],
            'costs_estimated' => $result['costs_estimated'],
            'revenue_recorded' => $result['revenue_recorded'],
            'margin_estimated' => $result['margin_estimated'],
            'is_reliable' => $result['is_reliable'],
            'missing_inputs' => $result['missing_inputs'],
            'data_quality' => $result['data_quality'],
            'state' => $result['is_reliable'] ? 'data_available' : 'insufficient_data',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingSync(Company $company): array
    {
        $summary = $this->sync->statusSummary($company);

        return [
            'pending' => $summary['pending'],
            'conflict' => $summary['conflict'],
            'failed' => $summary['failed'],
            'rejected' => $summary['rejected'],
            'unresolved_conflicts' => $summary['unresolved_conflicts'],
            'state' => $summary['pending'] === 0 && $summary['unresolved_conflicts'] === 0
                ? 'nothing_pending'
                : 'data_available',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function todayOperations(Request $request): array
    {
        $operations = FarmOperation::query()
            ->with(['parcel:id,name'])
            ->when($request->filled('assigned_to'), fn ($query) => $query->where('assigned_to', $request->integer('assigned_to')))
            ->whereIn('status', [FarmOperationStatus::Planned->value, FarmOperationStatus::InProgress->value])
            ->whereDate('planned_start_at', Carbon::today())
            ->orderBy('planned_start_at')
            ->limit(10)
            ->get();

        return [
            'count' => $operations->count(),
            'items' => $operations->map(static fn (FarmOperation $operation): array => [
                'id' => $operation->id,
                'title' => $operation->title,
                'op_type' => $operation->op_type,
                'status' => $operation->status,
                'parcel' => $operation->parcel?->name,
                'planned_start_at' => $operation->planned_start_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lateOperations(Request $request): array
    {
        $count = FarmOperation::query()
            ->when($request->filled('assigned_to'), fn ($query) => $query->where('assigned_to', $request->integer('assigned_to')))
            ->whereIn('status', [FarmOperationStatus::Planned->value, FarmOperationStatus::InProgress->value])
            ->whereNotNull('planned_end_at')
            ->where('planned_end_at', '<', Carbon::now())
            ->count();

        return ['count' => $count];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyState(): array
    {
        $hasSite = \App\Modules\FarmOs\Domain\Models\FarmSite::query()->exists();
        $hasParcel = Parcel::query()->exists();

        if (! $hasSite) {
            return ['code' => 'create_first_site', 'next_action' => 'farm.sites.create'];
        }

        if (! $hasParcel) {
            return ['code' => 'create_first_parcel', 'next_action' => 'farm.parcels.create'];
        }

        return ['code' => 'ready', 'next_action' => 'farm.operations.create'];
    }

    private function company(): Company
    {
        return app(FarmCompanyContext::class)->current();
    }
}
