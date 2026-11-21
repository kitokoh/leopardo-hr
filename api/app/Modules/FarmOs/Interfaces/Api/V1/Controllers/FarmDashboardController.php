<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Services\FarmCapabilityGate;
use App\Modules\FarmOs\Infrastructure\Context\FarmCompanyContext;
use App\Modules\FarmOs\Infrastructure\Services\FarmDashboardStatsService;
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
 *
 * Ce contrôleur ne porte que la décision « capacité activée → section
 * exposée » et la forme de la réponse ; le calcul des sections vit dans
 * `FarmDashboardStatsService`.
 */
final class FarmDashboardController
{
    public function __construct(
        private readonly FarmCapabilityGate $gate,
        private readonly FarmDashboardStatsService $stats,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $company = $this->company();
        $enabled = $this->gate->enabled($company);
        $now = Carbon::now();

        $sections = [];

        if (in_array(FarmCapability::WorkPlanning->value, $enabled, true)) {
            $sections['today_operations'] = $this->stats->todayOperations($request);
            $sections['late_operations'] = $this->stats->lateOperations($request);
        }

        if (in_array(FarmCapability::ParcelsCrops->value, $enabled, true)) {
            $sections['parcels'] = $this->stats->parcels();
            $sections['crop_cycles'] = $this->stats->cropCycles();
        }

        // Sections des modules LIVRÉS : chacune n'apparaît que si la capacité
        // correspondante est activée, et chacune porte un état honnête (donnée
        // disponible, vide utile, ou non calculable faute de données).
        if (in_array(FarmCapability::StocksInputs->value, $enabled, true)) {
            $sections['inputs_to_reorder'] = $this->stats->inputsToReorder($company);
        }

        if (in_array(FarmCapability::Equipment->value, $enabled, true)) {
            $sections['equipment_maintenance_due'] = $this->stats->equipmentMaintenanceDue($company, $now);
        }

        if (in_array(FarmCapability::Livestock->value, $enabled, true)) {
            $sections['livestock'] = $this->stats->livestock($company, $now);
        }

        if (in_array(FarmCapability::HarvestStorage->value, $enabled, true)) {
            $sections['harvest_stock'] = $this->stats->harvestStock($company);
        }

        if (in_array(FarmCapability::FinanceExpenses->value, $enabled, true)) {
            $sections['finance'] = $this->stats->finance($company);
        }

        // Opérations en attente de synchronisation : visible uniquement si le
        // mode hors ligne a réellement quelque chose à remonter.
        $sections['pending_sync'] = $this->stats->pendingSync($company);

        return response()->json([
            'data' => [
                'generated_at' => $now->toIso8601String(),
                'enabled_capabilities' => $enabled,
                'sections' => $sections,
                'empty_state' => $this->stats->emptyState(),
            ],
        ]);
    }

    private function company(): Company
    {
        return app(FarmCompanyContext::class)->current();
    }
}
