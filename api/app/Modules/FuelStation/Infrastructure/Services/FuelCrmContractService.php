<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelCustomer;
use App\Modules\FuelStation\Domain\Models\FuelCustomerVisit;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Support\Carbon;

/**
 * Intégration CRM client FuelStation (FUEL-016, issue #5810).
 *
 * CRM TENANT uniquement — aucune lecture des leads commerciaux Leopardo
 * (CRM plateforme, distinction ADR-CRM-002). Règles :
 *  - consentement marketing EXPLICITE horodaté (opt-in → opted_in_at,
 *    opt-out → opted_out_at) — aucun usage marketing sans consentement ;
 *  - visite = +1 point de fidélité, crédité UNE SEULE FOIS par visite
 *    (`idempotency_key` UNIQUE (company_id, idempotency_key)) ;
 *  - `external_id` UNIQUE (company_id, external_id) → rejeu idempotent
 *    d'un compte client (import).
 */
final class FuelCrmContractService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function registerCustomer(Employee $actor, FuelStation $station, array $data): FuelCustomer
    {
        $externalId = isset($data['external_id']) ? (string) $data['external_id'] : null;

        if ($externalId !== null) {
            $existing = FuelCustomer::query()
                ->where('company_id', $actor->company_id)
                ->where('external_id', $externalId)
                ->first();

            if ($existing instanceof FuelCustomer) {
                return $existing;
            }
        }

        $consent = (bool) ($data['marketing_consent'] ?? false);

        /** @var FuelCustomer $customer */
        $customer = FuelCustomer::query()->create(array_merge($data, [
            'company_id' => $actor->company_id,
            'station_id' => $station->id,
            'marketing_consent' => $consent,
            'opted_in_at' => $consent ? Carbon::now() : null,
            'loyalty_points' => (int) ($data['loyalty_points'] ?? 0),
            'status' => $data['status'] ?? FuelCustomer::STATUS_ACTIVE,
            'created_by' => $actor->id,
        ]));

        return $customer;
    }

    /**
     * Consentement marketing : opt-in/opt-out explicite et horodaté.
     */
    public function updateConsent(Employee $actor, FuelCustomer $customer, bool $consent): FuelCustomer
    {
        $customer->update([
            'marketing_consent' => $consent,
            'opted_in_at' => $consent ? Carbon::now() : $customer->opted_in_at,
            'opted_out_at' => $consent ? null : Carbon::now(),
        ]);

        return $customer->refresh();
    }

    /**
     * Enregistre une visite et crédite la fidélité (+1 point), une seule
     * fois par visite (idempotency_key).
     *
     * @param  array<string, mixed>  $data
     */
    public function recordVisit(Employee $actor, FuelCustomer $customer, FuelStation $station, array $data): FuelCustomerVisit
    {
        $idempotencyKey = isset($data['idempotency_key']) ? (string) $data['idempotency_key'] : null;

        if ($idempotencyKey !== null) {
            $existing = FuelCustomerVisit::query()
                ->where('company_id', $actor->company_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof FuelCustomerVisit) {
                return $existing;
            }
        }

        /** @var FuelCustomerVisit $visit */
        $visit = FuelCustomerVisit::query()->create([
            'company_id' => $actor->company_id,
            'customer_id' => $customer->id,
            'station_id' => $station->id,
            'visited_at' => isset($data['visited_at'])
                ? Carbon::parse((string) $data['visited_at'])
                : Carbon::now(),
            'notes' => $data['notes'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'created_by' => $actor->id,
        ]);

        $customer->increment('loyalty_points');

        return $visit;
    }
}
