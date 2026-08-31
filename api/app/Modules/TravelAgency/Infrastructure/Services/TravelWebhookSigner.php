<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

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
    /**
     * @param  array<string, mixed>  $payload
     */
    public function sign(array $payload, string $secret): string
    {
        return hash_hmac('sha256', $this->canonicalPayload($payload), $secret);
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
        $expected = hash_hmac('sha256', $canonicalPayload, $secret);

        return hash_equals($expected, $signature);
    }
}
