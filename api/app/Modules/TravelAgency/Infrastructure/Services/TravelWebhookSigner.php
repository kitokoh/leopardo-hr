<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Contracts\Queue\OutboundWebhookSigner;
use App\Jobs\Services\HmacWebhookSigner;

/**
 * TRAVEL-806 (#6097) — Signature HMAC des webhooks transporteurs.
 *
 * En-têtes : `X-Travel-Signature` = HMAC-SHA256(payload JSON, secret),
 * `X-Travel-Timestamp` (anti-rejeu, fenêtre de 5 min côté récepteur).
 * Le secret de signature est stocké hashé en base (secret_hash) — jamais
 * restitué par l'API.
 */
final class TravelWebhookSigner
{
    public function __construct(
        private readonly OutboundWebhookSigner $signer = new HmacWebhookSigner,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sign(array $payload, string $secret): string
    {
        // BOS-021 (#8210) : primitive HMAC mutualisée (BC-14). La
        // canonicalisation reste propre à Travel — c'est elle qui définit
        // le contrat public `X-Travel-Signature`.
        return $this->signer->sign($secret, $this->canonicalPayload($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function canonicalPayload(array $payload): string
    {
        ksort($payload);

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function verify(string $signature, string $canonicalPayload, string $secret): bool
    {
        return $this->signer->verify($secret, $canonicalPayload, $signature);
    }
}
