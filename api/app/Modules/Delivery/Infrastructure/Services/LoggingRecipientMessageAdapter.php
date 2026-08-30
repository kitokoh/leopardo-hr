<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Infrastructure\Services;

use App\Modules\Delivery\Domain\Contracts\RecipientMessageContract;
use Illuminate\Support\Facades\Log;

/**
 * Implémentation par défaut du contrat BC-13 (DELIVERY-206, issue #6290).
 *
 * Seam : tant que les adaptateurs SMS/WhatsApp (Twilio/WhatsApp Cloud API)
 * ne sont pas branchés sur les destinataires EXTERNES, l'envoi est
 * journalisé en champ structuré — le numéro n'apparaît JAMAIS dans le
 * message (haché), seulement dans le payload métier.
 */
final class LoggingRecipientMessageAdapter implements RecipientMessageContract
{
    public function send(string $phone, string $templateKey, array $context): bool
    {
        Log::channel('structured')->info('delivery.notification.send', [
            'template_key' => $templateKey,
            'recipient_hash' => substr(hash('sha256', $phone), 0, 12),
            'status' => 'seam-not-wired',
        ]);

        return true;
    }
}
