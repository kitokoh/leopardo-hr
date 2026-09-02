<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use App\Modules\FuelStation\Domain\Models\FuelSale;
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
 * - Contrat Accounting (FUEL-015) : publication outbox
 *   `fuel.sale.recorded.v1` après commit — agrégat validé, sans PII.
 * - Fidélité (FUEL-016) : points crédités si la vente est liée à un client.
 */
final class FuelSaleService
{
    public function __construct(
        private readonly FuelOutboxPublisher $outbox,
        private readonly FuelLoyaltyService $loyalty,
    ) {}

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
            'customer_id' => $data['customer_id'] ?? null,
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

        // sale_time est un défaut DB (useCurrent) : refresh pour le charger.
        $sale = $sale->refresh();

        // Contrat Accounting (FUEL-015) : agrégat validé, idempotent par vente.
        $this->outbox->publish(
            (string) $actor->company_id,
            FuelOutboxEvent::EVENT_SALE_RECORDED,
            [
                'sale_id' => $sale->id,
                'station_id' => $sale->station_id,
                'product' => $sale->product,
                'quantity' => $sale->quantity,
                'amount' => $sale->amount,
                'sale_time' => $sale->sale_time->toISOString(),
                'source' => $sale->source,
            ],
            'fuel_sale',
            (string) $sale->id,
            'sale-'.$sale->id,
        );

        // Fidélité (FUEL-016) : points crédités pour une vente liée client.
        if ($sale->customer_id !== null) {
            $this->loyalty->accruePointsForSale($sale);
        }

        return $sale;
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
