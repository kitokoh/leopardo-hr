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
            'customer_contact_id' => $data['customer_contact_id'] ?? null,
            'marketing_consent' => (bool) ($data['marketing_consent'] ?? false),
        ]);

        $this->publishCustomerActivity($sale);

        // sale_time est un défaut DB (useCurrent) : refresh pour le charger.
        return $sale->refresh();
    }

    /**
     * FUEL-016 (#5810) — activité client (CRM tenant) par outbox.
     *
     * Événement `fuel.customer.activity.v1` uniquement si un contact client
     * tenant est référencé ET que l'opt-in marketing est explicite (RGPD).
     * Payload sans PII (référence contact_id) ; aucune lecture des leads
     * commerciaux plateforme (crm_leads). Idempotent par vente.
     */
    private function publishCustomerActivity(FuelSale $sale): void
    {
        if ($sale->customer_contact_id === null || ! $sale->marketing_consent) {
            return;
        }

        FuelOutboxEvent::query()->firstOrCreate(
            ['company_id' => $sale->company_id, 'idempotency_key' => 'fuel-customer-activity:'.$sale->id],
            [
                'event_type' => 'fuel.customer.activity.v1',
                'payload_redacted' => [
                    'sale_id' => $sale->id,
                    'station_id' => $sale->station_id,
                    'customer_contact_id' => $sale->customer_contact_id,
                    'amount' => $sale->amount,
                    'product' => $sale->product,
                    'sale_time' => $sale->sale_time?->toIso8601String(),
                ],
                'status' => FuelOutboxEvent::STATUS_PENDING,
                'attempts' => 0,
                'available_at' => now(),
            ],
        );
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
