<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Contracts;

/**
 * Contrat BC-13 COMMS pour les notifications destinataire (DELIVERY-206,
 * issue #6290).
 *
 * Envoi d'un message (SMS/WhatsApp) à un destinataire externe. L'opt-out est
 * vérifié AVANT l'envoi (outbox) ; les templates sont versionnés ; aucun
 * log ne doit contenir la PII (numéro, adresse).
 */
interface RecipientMessageContract
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function send(string $phone, string $templateKey, array $context): bool;
}
