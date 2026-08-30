<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelCustomer;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use App\Modules\FuelStation\Domain\Models\FuelSale;

/**
 * Clients & fidélité FuelStation (CRM client tenant) — FUEL-016 (#5810).
 *
 * - Upsert idempotent par `external_id` (synchronisation POS/ERP).
 * - Consentement marketing explicite (opt-in RGPD) ; aucun usage marketing
 *   sans consentement. Le changement de consentement est versionné (outbox
 *   fuel.customer.consent.updated.v1).
 * - Points de fidélité : crédités sur vente liée (customer_id), cumul
 *   auditables, dépense/recharge bornée.
 * - JAMAIS de lecture du CRM commercial Leopardo (ADR-CRM-002) : le module
 *   travaille uniquement sur fuel_customers.
 */
final class FuelLoyaltyService
{
    /** Points par litre vendu (réglage pilotage). */
    public const POINTS_PER_LITRE = 1;

    public function __construct(private readonly FuelOutboxPublisher $outbox) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(Employee $actor, array $data): FuelCustomer
    {
        $companyId = (string) $actor->company_id;
        $externalId = is_string($data['external_id'] ?? null) ? $data['external_id'] : '';

        if ($externalId === '') {
            abort(422, 'CUSTOMER_EXTERNAL_ID_REQUIRED');
        }

        /** @var FuelCustomer|null $customer */
        $customer = FuelCustomer::query()
            ->where('company_id', $companyId)
            ->where('external_id', $externalId)
            ->first();

        $created = $customer === null;

        $customer = $customer ?? new FuelCustomer;

        $customer->forceFill([
            'company_id' => $companyId,
            'station_id' => $data['station_id'] ?? $customer->station_id,
            'external_id' => $externalId,
            'full_name' => is_string($data['full_name'] ?? null) ? $data['full_name'] : $customer->full_name,
            'phone' => $data['phone'] ?? $customer->phone,
            'email' => $data['email'] ?? $customer->email,
            'metadata' => $data['metadata'] ?? $customer->metadata,
        ])->save();

        $this->outbox->publish(
            $companyId,
            $created ? FuelOutboxEvent::EVENT_CUSTOMER_CREATED : FuelOutboxEvent::EVENT_CUSTOMER_CONSENT_UPDATED,
            [
                'customer_id' => $customer->id,
                'external_id' => $externalId,
                'marketing_consent' => (bool) $customer->marketing_consent,
            ],
            'fuel_customer',
            (string) $customer->id,
            ($created ? 'customer-created-' : 'customer-updated-').$customer->id,
        );

        return $customer->refresh();
    }

    public function setConsent(Employee $actor, FuelCustomer $customer, bool $consent): FuelCustomer
    {
        if ($customer->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $customer->forceFill(['marketing_consent' => $consent])->save();

        $this->outbox->publish(
            (string) $customer->company_id,
            FuelOutboxEvent::EVENT_CUSTOMER_CONSENT_UPDATED,
            [
                'customer_id' => $customer->id,
                'external_id' => $customer->external_id,
                'marketing_consent' => $consent,
            ],
            'fuel_customer',
            (string) $customer->id,
            'consent-'.$customer->id.'-'.($consent ? 'yes' : 'no'),
        );

        return $customer->refresh();
    }

    /**
     * Crédite les points de fidélité d'une vente liée (idempotent par vente).
     */
    public function accruePointsForSale(FuelSale $sale): void
    {
        if ($sale->customer_id === null) {
            return;
        }

        /** @var FuelCustomer|null $customer */
        $customer = FuelCustomer::query()
            ->where('company_id', $sale->company_id)
            ->where('id', $sale->customer_id)
            ->first();

        if (! $customer instanceof FuelCustomer) {
            return;
        }

        $points = max(0, (int) round($sale->quantity * self::POINTS_PER_LITRE));

        if ($points === 0) {
            return;
        }

        $customer->increment('loyalty_points', $points);
    }

    /**
     * Dépense de points (récompense) — bornée au solde, jamais négative.
     */
    public function redeemPoints(Employee $actor, FuelCustomer $customer, int $points, string $reason): FuelCustomer
    {
        if ($customer->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        if ($points <= 0) {
            abort(422, 'REDEEM_POINTS_POSITIVE');
        }

        if ($customer->loyalty_points < $points) {
            abort(422, 'INSUFFICIENT_LOYALTY_POINTS');
        }

        $customer->decrement('loyalty_points', $points);

        return $customer->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function consentFor(FuelCustomer $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'marketing_consent' => (bool) $customer->marketing_consent,
            'updated_at' => $customer->updated_at?->toISOString(),
        ];
    }
}
