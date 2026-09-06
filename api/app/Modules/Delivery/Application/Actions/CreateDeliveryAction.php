<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Application\Actions;

use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\ValueObjects\DeliveryReference;
use Illuminate\Database\QueryException;

/**
 * DELIVERY-201 (#6285) — création d'une livraison tenant, idempotente.
 *
 * Extraite de DeliveryController::store (couche Application vide, #6898).
 * Comportement conservé à l'identique :
 *  - contrats sources (restaurant RST-…, retail POS-…, e-commerce, crm) :
 *    une commande source crée sa livraison une seule fois — unique
 *    (company_id, source, source_reference) ; le rejeu (webhook, retry)
 *    retourne l'existante ;
 *  - référence DLV-YYYY-NNNNNN : séquence du jour par tenant (l'index unique
 *    (company_id, reference) protège la course) ;
 *  - course sur l'unicité source (23505) : le perdant refetch et retourne
 *    l'existante.
 *
 * Le statut HTTP (200 rejeu / 201 création) reste décidé par le contrôleur
 * via `Delivery::$wasRecentlyCreated`.
 */
final class CreateDeliveryAction
{
    /**
     * @param  array<string, mixed>  $validated  payload validé (DeliveryStoreRequest)
     */
    public function execute(string $companyId, array $validated): Delivery
    {
        $source = (string) $validated['source'];
        $sourceReference = $validated['source_reference'] ?? null;

        if ($source !== 'manual' && is_string($sourceReference) && $sourceReference !== '') {
            $existing = $this->findBySource($companyId, $source, $sourceReference);

            if ($existing !== null) {
                return $existing;
            }
        }

        $sequence = $this->nextSequence($companyId);

        try {
            return $this->insert($companyId, $validated, $sequence);
        } catch (QueryException $exception) {
            // Course sur l'unicité (company, source, source_reference) : deux
            // webhooks simultanés → le perdant refetch et retourne l'existante.
            if ($source !== 'manual' && $exception->getCode() === '23505') {
                $existing = $this->findBySource($companyId, $source, $sourceReference);

                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $exception;
        }
    }

    private function findBySource(string $companyId, string $source, string $sourceReference): ?Delivery
    {
        /** @var Delivery|null $existing */
        $existing = Delivery::query()
            ->where('company_id', $companyId)
            ->where('source', $source)
            ->where('source_reference', $sourceReference)
            ->first();

        return $existing;
    }

    private function nextSequence(string $companyId): int
    {
        return (int) Delivery::query()
            ->where('company_id', $companyId)
            ->whereYear('created_at', now()->year)
            ->max('id') + 1;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function insert(string $companyId, array $validated, int $sequence): Delivery
    {
        /** @var Delivery $delivery */
        $delivery = Delivery::query()->create([
            'company_id' => $companyId,
            'reference' => DeliveryReference::fromSequence(now()->year, $sequence)->toString(),
            'source' => $validated['source'],
            'source_reference' => $validated['source_reference'] ?? null,
            'type' => $validated['type'],
            'status' => 'created',
            'weight_grams' => $validated['weight_grams'] ?? null,
            'volume_cm3' => $validated['volume_cm3'] ?? null,
            'declared_value_minor' => $validated['declared_value_minor'] ?? 0,
            'cod_amount_minor' => $validated['cod_amount_minor'] ?? null,
            'pickup_contact' => $validated['pickup_contact'] ?? null,
            'pickup_address' => $validated['pickup_address'] ?? null,
            'dropoff_contact' => $validated['dropoff_contact'],
            'dropoff_phone' => $validated['dropoff_phone'] ?? null,
            'dropoff_address' => $validated['dropoff_address'],
            'window_from' => $validated['window_from'] ?? null,
            'window_to' => $validated['window_to'] ?? null,
            'idempotency_key' => $validated['idempotency_key'] ?? null,
        ]);

        return $delivery;
    }
}
