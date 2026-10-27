<?php

declare(strict_types=1);

namespace App\Jobs\Services;

use App\Contracts\Queue\OutboundWebhookSigner;

/**
 * BOS-021 (#8210) — implémentation unique de la primitive de signature des
 * webhooks sortants (BC-14 INTEGRATION).
 *
 * Remplace les deux copies HMAC qui vivaient dans Billing et TravelAgency.
 * Le schéma cryptographique est volontairement figé sur HMAC-SHA256 : c'est
 * ce que les deux producteurs émettaient déjà, et c'est ce que les
 * consommateurs partenaires vérifient. Changer d'algorithme ici casserait
 * tous les abonnés — un changement d'algorithme exigerait un versionnement
 * de signature explicite (voir docs/api/VERSIONING.md § 5).
 */
final class HmacWebhookSigner implements OutboundWebhookSigner
{
    /**
     * Algorithme unique des webhooks sortants Leopardo.
     */
    public const ALGORITHM = 'sha256';

    public function sign(string $secret, string $signedPayload): string
    {
        return hash_hmac(self::ALGORITHM, $signedPayload, $secret);
    }

    public function verify(string $secret, string $signedPayload, string $providedSignature): bool
    {
        return hash_equals($this->sign($secret, $signedPayload), $providedSignature);
    }
}
