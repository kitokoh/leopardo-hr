<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Enums\FarmOperationStatus;
use App\Modules\FarmOs\Domain\Models\CropCycle;
use App\Modules\FarmOs\Domain\Models\FarmOperation;
use App\Modules\FarmOs\Domain\Models\Parcel;
use App\Modules\FarmOs\Domain\Services\FarmCapabilityGate;
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

        // Sections des modules non encore livrés (stocks, matériel, élevage,
        // ventes, coûts) : déclarées EXPLICITEMENT non disponibles plutôt que
        // simulées — exigence d'honnêteté fonctionnelle.
        $sections['pending_capabilities'] = array_values(array_filter([
            in_array(FarmCapability::StocksInputs->value, $enabled, true) ? 'stocks_inputs' : null,
            in_array(FarmCapability::Equipment->value, $enabled, true) ? 'equipment' : null,
            in_array(FarmCapability::Livestock->value, $enabled, true) ? 'livestock' : null,
            in_array(FarmCapability::HarvestStorage->value, $enabled, true) ? 'harvest_storage' : null,
            in_array(FarmCapability::SalesCustomers->value, $enabled, true) ? 'sales_customers' : null,
            in_array(FarmCapability::FinanceExpenses->value, $enabled, true) ? 'finance_expenses' : null,
        ]));

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
