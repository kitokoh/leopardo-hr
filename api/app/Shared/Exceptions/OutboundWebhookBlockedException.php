<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;

/**
 * BOS-021 (#8210) — l'URL d'un webhook sortant a été refusée par la garde
 * anti-SSRF au moment de la livraison.
 *
 * Distincte d'une erreur réseau : une erreur réseau est transitoire (on
 * réessaie), un hôte interdit est une décision (on n'insiste pas). Les
 * producteurs doivent donc la traiter comme un échec définitif — Billing
 * désactive l'endpoint, Travel le passe en dead-letter — et non comme un
 * incident à retenter.
 */
final class OutboundWebhookBlockedException extends RuntimeException
{
    public static function forUrl(string $url): self
    {
        return new self(sprintf(
            'Webhook delivery blocked: URL is not an allowed public https endpoint (%s).',
            $url,
        ));
    }
}
