<?php

declare(strict_types=1);

namespace App\Shared\DTOs;

/**
 * BOS-021 (#8210) — résultat d'une tentative de livraison de webhook sortant.
 *
 * Le transport ne décide de RIEN : il rapporte le code HTTP, le corps de
 * réponse (borné) et la durée. C'est le producteur qui sait ce qu'un `200`
 * signifie pour son contrat (Billing réactive l'endpoint, Travel passe la
 * livraison en `SENT`), et c'est lui qui possède son journal de livraison.
 *
 * Unifier la décision aurait forcé les deux verticales à partager un modèle
 * de données qu'elles n'ont pas : Billing journalise dans `webhook_deliveries`,
 * Travel dans `travel_webhook_deliveries`, avec des statuts différents.
 */
final readonly class OutboundWebhookResponse
{
    public function __construct(
        public int $status,
        public string $body,
        public int $durationMs,
    ) {}

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
