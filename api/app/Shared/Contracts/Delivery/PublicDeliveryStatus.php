<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Delivery;

/**
 * DTO PUBLIC fail-closed du suivi de livraison BC-26 (#7811).
 *
 * Uniquement le statut et les horodatages publics — AUCUNE donnée interne
 * (pas d'id, pas de company_id, pas de livreur, pas de montants, pas
 * d'adresses). Consommé par la page de suivi publique marketplace
 * (`GET /public/market/orders/{reference}`).
 */
final class PublicDeliveryStatus
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $createdAt,
        public readonly ?string $deliveredAt,
        public readonly ?string $failedAt,
        public readonly ?string $returnedAt,
    ) {}

    /**
     * @return array{status: string, created_at: string|null, delivered_at: string|null, failed_at: string|null, returned_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'delivered_at' => $this->deliveredAt,
            'failed_at' => $this->failedAt,
            'returned_at' => $this->returnedAt,
        ];
    }
}
