<?php

declare(strict_types=1);

/**
 * Routes du module Farm OS (verticale agricole, BC-37 AGRICULTURE — spec
 * docs/specifications/SOLUTION_FARM_OS.md §7).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 (jamais de
 * re-préfixe `v1`).
 *
 * Gate `module.farm_os` : feature flag companies.features.farm_os posé par
 * `SolutionActivator` à l'activation de la solution (fail-closed — tenant sans
 * le flag ⇒ 403). Les capacités (13 outils activables) sont contrôlées PLUS FIN
 * dans les contrôleurs, via `FarmCapabilityGate` : un outil non activé n'est
 * jamais accessible, même par appel direct de l'API.
 */

use App\Modules\FarmOs\Interfaces\Api\V1\Controllers\CropCycleController;
use App\Modules\FarmOs\Interfaces\Api\V1\Controllers\FarmDashboardController;
use App\Modules\FarmOs\Interfaces\Api\V1\Controllers\FarmEquipmentController;
use App\Modules\FarmOs\Interfaces\Api\V1\Controllers\FarmFinanceController;
use App\Modules\FarmOs\Interfaces\Api\V1\Controllers\FarmHarvestController;
use App\Modules\FarmOs\Interfaces\Api\V1\Controllers\FarmLivestockController;
use App\Modules\FarmOs\Interfaces\Api\V1\Controllers\FarmInputStockController;
use App\Modules\FarmOs\Interfaces\Api\V1\Controllers\FarmOperationController;
use App\Modules\FarmOs\Interfaces\Api\V1\Controllers\FarmSettingsController;
use App\Modules\FarmOs\Interfaces\Api\V1\Controllers\FarmSiteController;
use App\Modules\FarmOs\Interfaces\Api\V1\Controllers\ParcelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module.farm_os'])
    ->prefix('farm')
    ->group(function (): void {
        // ── Réglages : activités déclarées, outils activés, référentiels ──
        Route::get('/settings', [FarmSettingsController::class, 'show']);
        Route::put('/activities', [FarmSettingsController::class, 'updateActivities']);
        Route::get('/capabilities/available', [FarmSettingsController::class, 'availableCapabilities']);
        Route::put('/capabilities', [FarmSettingsController::class, 'updateCapabilities']);
        // Étape 4 — provisioning idempotent de l'espace d'exploitation
        // (réutilise SolutionActivator, cf. ProvisionFarmOs).
        Route::post('/provision', [FarmSettingsController::class, 'provision']);

        // ── Tableau de bord de l'exploitation ──
        Route::get('/dashboard', [FarmDashboardController::class, 'index']);

        // ── Exploitations / sites ──
        Route::get('/sites', [FarmSiteController::class, 'index']);
        Route::post('/sites', [FarmSiteController::class, 'store']);
        Route::get('/sites/{site}', [FarmSiteController::class, 'show']);

        // ── Parcelles ──
        Route::get('/parcels', [ParcelController::class, 'index']);
        Route::post('/sites/{site}/parcels', [ParcelController::class, 'store']);
        Route::get('/parcels/{parcel}', [ParcelController::class, 'show']);
        Route::get('/parcels/{parcel}/history', [ParcelController::class, 'history']);

        // ── Cycles culturaux ──
        Route::get('/crop-cycles', [CropCycleController::class, 'index']);
        Route::post('/parcels/{parcel}/crop-cycles', [CropCycleController::class, 'store']);
        Route::post('/crop-cycles/{cycle}/close', [CropCycleController::class, 'close']);

        // ── Opérations agricoles (interventions) ──
        Route::get('/operations', [FarmOperationController::class, 'index']);
        Route::get('/operations/today', [FarmOperationController::class, 'today']);
        Route::get('/operations/late', [FarmOperationController::class, 'late']);
        Route::post('/sites/{site}/operations', [FarmOperationController::class, 'store']);
        Route::get('/operations/{operation}', [FarmOperationController::class, 'show']);
        Route::post('/operations/{operation}/start', [FarmOperationController::class, 'start']);
        Route::post('/operations/{operation}/complete', [FarmOperationController::class, 'complete']);
        Route::post('/operations/{operation}/skip', [FarmOperationController::class, 'skip']);
        Route::post('/operations/{operation}/cancel', [FarmOperationController::class, 'cancel']);
        Route::post('/operations/{operation}/assign', [FarmOperationController::class, 'assign']);
        Route::post('/operations/{operation}/progress', [FarmOperationController::class, 'progress']);
        Route::post('/operations/{operation}/report-problem', [FarmOperationController::class, 'reportProblem']);

        // ── T2.1 Intrants et stocks agricoles ──
        // Le journal des mouvements est en LECTURE SEULE : on n'y écrit que par
        // les actions de stock (receive / issue / adjust), jamais par un PUT.
        Route::get('/inputs', [FarmInputStockController::class, 'index']);
        Route::post('/inputs', [FarmInputStockController::class, 'store']);
        Route::get('/inputs/alerts', [FarmInputStockController::class, 'alerts']);
        Route::get('/inputs/{input}', [FarmInputStockController::class, 'show']);
        Route::get('/inputs/{input}/movements', [FarmInputStockController::class, 'movements']);
        Route::post('/inputs/{input}/receive', [FarmInputStockController::class, 'receive']);
        Route::post('/inputs/{input}/issue', [FarmInputStockController::class, 'issue']);
        Route::post('/inputs/{input}/adjust', [FarmInputStockController::class, 'adjust']);

        // ── T2.2 Materiel agricole ──
        // Les releves de compteur et l'historique de maintenance sont en LECTURE
        // SEULE : on n'y ecrit que par les actions dediees (usage / maintenance /
        // breakdown), jamais par un PUT. Aucune telemetrie : `meter_source` ne
        // peut valoir que `manual` ou `imported`.
        Route::get('/equipment', [FarmEquipmentController::class, 'index']);
        Route::post('/equipment', [FarmEquipmentController::class, 'store']);
        Route::get('/equipment/maintenance-due', [FarmEquipmentController::class, 'maintenanceDue']);
        Route::get('/equipment/{equipment}', [FarmEquipmentController::class, 'show']);
        Route::post('/equipment/{equipment}/usage', [FarmEquipmentController::class, 'recordUsage']);
        Route::post('/equipment/{equipment}/meter-reset', [FarmEquipmentController::class, 'resetMeter']);
        Route::post('/equipment/{equipment}/maintenances', [FarmEquipmentController::class, 'storeMaintenance']);
        Route::post('/equipment/{equipment}/breakdowns', [FarmEquipmentController::class, 'storeBreakdown']);
        Route::post('/equipment/{equipment}/documents', [FarmEquipmentController::class, 'storeDocument']);
        Route::post('/equipment/maintenances/{maintenance}/complete', [FarmEquipmentController::class, 'completeMaintenance']);
        Route::post('/equipment/breakdowns/{breakdown}/resolve', [FarmEquipmentController::class, 'resolveBreakdown']);

        // ── T3.1 Elevage ──
        // L'effectif present n'est JAMAIS modifie directement : il decoule du
        // journal d'evenements (POST /livestock/batches/{batch}/events). Aucune
        // duree de gestation, aucun dosage et aucun delai d'attente ne sont
        // calcules par Farm OS.
        Route::get('/livestock/batches', [FarmLivestockController::class, 'indexBatches']);
        Route::post('/livestock/batches', [FarmLivestockController::class, 'storeBatch']);
        Route::get('/livestock/batches/{batch}', [FarmLivestockController::class, 'showBatch']);
        Route::post('/livestock/batches/{batch}/events', [FarmLivestockController::class, 'recordEvent']);
        Route::post('/livestock/batches/{batch}/health-events', [FarmLivestockController::class, 'storeHealthEvent']);
        Route::post('/livestock/batches/{batch}/reproduction-events', [FarmLivestockController::class, 'storeReproductionEvent']);
        Route::post('/livestock/batches/{batch}/production', [FarmLivestockController::class, 'storeProduction']);
        Route::post('/livestock/batches/{batch}/feed', [FarmLivestockController::class, 'storeFeed']);
        Route::get('/livestock/animals', [FarmLivestockController::class, 'indexAnimals']);
        Route::post('/livestock/animals', [FarmLivestockController::class, 'storeAnimal']);
        Route::get('/livestock/summary', [FarmLivestockController::class, 'summary']);

        // ── T3.2 Recoltes, entreposage et tracabilite ──
        // La quantite recoltee est FIGEE a la creation du lot : aucun PUT ne
        // permet de la modifier. La quantite restante decoule du journal des
        // mouvements. Une vente doit referencer un document du service de ventes
        // EXISTANT (sale_document_id) : Farm OS ne cree aucun document commercial.
        Route::get('/harvests/locations', [FarmHarvestController::class, 'indexLocations']);
        Route::post('/harvests/locations', [FarmHarvestController::class, 'storeLocation']);
        Route::get('/harvests/lots', [FarmHarvestController::class, 'indexLots']);
        Route::post('/harvests/lots', [FarmHarvestController::class, 'storeLot']);
        Route::get('/harvests/lots/{lot}', [FarmHarvestController::class, 'showLot']);
        Route::post('/harvests/lots/{lot}/movements', [FarmHarvestController::class, 'recordMovement']);
        Route::get('/harvests/summary', [FarmHarvestController::class, 'summary']);

        // ── T4.1 Achats, ventes, depenses et rentabilite ──
        // AUCUN document commercial n'est cree ici : les ventes et les achats
        // sont REFERENCÉS par identifiant vers les services existants
        // (sale_document_id, purchase_document_id, supplier_id). La rentabilite
        // expose separement reel / estime / manquant.
        Route::get('/finance/campaigns', [FarmFinanceController::class, 'indexCampaigns']);
        Route::post('/finance/campaigns', [FarmFinanceController::class, 'storeCampaign']);
        Route::get('/finance/expenses', [FarmFinanceController::class, 'indexExpenses']);
        Route::post('/finance/expenses', [FarmFinanceController::class, 'storeExpense']);
        Route::get('/finance/sale-links', [FarmFinanceController::class, 'indexSaleLinks']);
        Route::post('/finance/sale-links', [FarmFinanceController::class, 'storeSaleLink']);
        Route::get('/finance/profitability', [FarmFinanceController::class, 'profitability']);
        Route::get('/finance/profitability-by-campaign', [FarmFinanceController::class, 'profitabilityByCampaign']);
    });
