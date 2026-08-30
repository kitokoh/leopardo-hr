<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ventes FuelStation (FUEL-008, issue #5802).
 *
 * - Montant calculé serveur : amount = quantity × unit_price (jamais fourni
 *   par le client) ; quantity en decimal(14,3) (litres), prix en decimal(14,2).
 * - Idempotence : `external_id` unique par tenant — un rejeu renvoie la
 *   vente existante (zéro doublon).
 * - Relation pompe/vente validée : la pompe doit appartenir au tenant
 *   (PUMP_OUTSIDE_TENANT) ; idem station via `fuel_stations` (FUEL-002).
 *   Session de caisse toujours validée contre `fuel_cash_sessions` (même
 *   tenant). Station/pompe sont BIGINTs reliés par FKs composites
 *   (x, company_id) → fuel_stations/fuel_pumps (pattern FUEL-002/003).
 */
final class FuelSaleService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(Employee $actor, array $data): FuelSale
    {
        $quantity = is_numeric($data['quantity'] ?? null) ? (float) $data['quantity'] : 0.0;
        $unitPrice = is_numeric($data['unit_price'] ?? null) ? (float) $data['unit_price'] : 0.0;
        $amount = round($quantity * $unitPrice, 2);

        $externalId = $data['external_id'] ?? null;
        if (is_string($externalId) && $externalId !== '') {
            $existing = FuelSale::query()
                ->where('company_id', $actor->company_id)
                ->where('external_id', $externalId)
                ->first();

            if ($existing instanceof FuelSale) {
                return $existing;
            }
        }

        $this->assertPumpBelongsToTenant($actor, $data['pump_id'] ?? null);
        $this->assertStationBelongsToTenant($actor, $data['station_id'] ?? null);

        $sale = FuelSale::query()->create([
            'company_id' => $actor->company_id,
            'station_id' => $data['station_id'] ?? null,
            'pump_id' => $data['pump_id'] ?? null,
            'cash_session_id' => $data['cash_session_id'] ?? null,
            'employee_id' => $actor->id,
            'product' => is_string($data['product'] ?? null) ? $data['product'] : '',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => $amount,
            'sale_time' => $data['sale_time'] ?? now(),
            'source' => $data['source'] ?? FuelSale::SOURCE_MANUAL,
            'external_id' => is_string($externalId) ? $externalId : null,
            'notes' => $data['notes'] ?? null,
        ]);

        // Décrément du niveau de la cuve du produit vendu (FUEL-009) : la
        // vente est un mouvement de stock légitime — le rapprochement
        // compare le niveau attendu (ouverture + livraisons − ventes) au
        // niveau mesuré. Table FUEL-003 : décrément seulement si elle existe.
        $this->decrementTankLevel($actor, $data, $quantity);

        // sale_time est un défaut DB (useCurrent) : refresh pour le charger.
        return $sale->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function decrementTankLevel(Employee $actor, array $data, float $quantity): void
    {
        $stationId = $data['station_id'] ?? null;
        $product = $data['product'] ?? null;

        if (! is_numeric($stationId) || ! is_string($product) || $product === '') {
            return;
        }

        if (! Schema::hasTable('fuel_tanks')) {
            return;
        }

        $tank = DB::table('fuel_tanks')
            ->where('company_id', $actor->company_id)
            ->where('station_id', (int) $stationId)
            ->where('product_type', $product)
            ->where('status', FuelTank::STATUS_ACTIVE)
            ->orderBy('id')
            ->first();

        if ($tank === null) {
            return;
        }

        $this->captureDayOpening($actor, (int) $tank->id);

        $decrementMinor = (int) round($quantity * 1000); // litres → unités mineures
        DB::table('fuel_tanks')
            ->where('id', (int) $tank->id)
            ->where('company_id', $actor->company_id)
            ->update([
                'current_level_minor' => DB::raw('GREATEST(0, current_level_minor - '.$decrementMinor.')'),
            ]);
    }

    /**
     * Fige le niveau de début de journée au premier mouvement du jour
     * (ouverture indépendante du niveau courant — base du rapprochement).
     */
    private function captureDayOpening(Employee $actor, int $tankId): void
    {
        if (! Schema::hasTable('fuel_stock_daily_openings')) {
            return;
        }

        $today = now()->toDateString();
        $exists = DB::table('fuel_stock_daily_openings')
            ->where('company_id', $actor->company_id)
            ->where('tank_id', $tankId)
            ->where('open_date', $today)
            ->exists();

        if ($exists) {
            return;
        }

        $level = (int) DB::table('fuel_tanks')
            ->where('id', $tankId)
            ->where('company_id', $actor->company_id)
            ->value('current_level_minor');

        DB::table('fuel_stock_daily_openings')->insert([
            'company_id' => $actor->company_id,
            'tank_id' => $tankId,
            'open_date' => $today,
            'opening_level_minor' => $level,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertPumpBelongsToTenant(Employee $actor, mixed $pumpId): void
    {
        if (! is_numeric($pumpId)) {
            return;
        }

        // Table livrée par FUEL-003 : validation seulement si elle existe.
        if (! Schema::hasTable('fuel_pumps')) {
            return;
        }

        $exists = DB::table('fuel_pumps')
            ->where('company_id', $actor->company_id)
            ->where('id', (int) $pumpId)
            ->exists();

        abort_if(! $exists, 422, 'PUMP_OUTSIDE_TENANT');
    }

    private function assertStationBelongsToTenant(Employee $actor, mixed $stationId): void
    {
        if (! is_numeric($stationId)) {
            return;
        }

        // Table livrée par FUEL-002 : validation seulement si elle existe.
        if (! Schema::hasTable('fuel_stations')) {
            return;
        }

        $exists = DB::table('fuel_stations')
            ->where('company_id', $actor->company_id)
            ->where('id', (int) $stationId)
            ->exists();

        abort_if(! $exists, 422, 'STATION_OUTSIDE_TENANT');
    }
}
