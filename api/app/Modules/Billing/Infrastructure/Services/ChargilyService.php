<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Services;

use Illuminate\Support\Facades\Log;

/**
 * Chargily payment integration service.
 *
 * Handles signature verification for Chargily webhooks using HMAC-SHA256.
 * Doc: https://dev.chargily.com/
 */
class ChargilyService
{
    private string $webhookSecret;

    public function __construct()
    {
        $this->webhookSecret = (string) config('services.chargily.webhook_secret');
    }

    /**
     * Verify the Chargily webhook signature.
     *
     * Chargily sends the signature as: X-Chargily-Signature: sha256=<hmac>
     *
     * @return array<string, mixed>|null Returns parsed payload on success, null on failure
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): ?array
    {
        if (empty($this->webhookSecret)) {
            // #2615 fail-closed : secret absent = webhook non vérifiable = on
            // REJETTE (null). Un secret vide ne doit jamais accepter un
            // payload (fail-open = signature by-passable en prod).
            Log::error('Chargily: Webhook secret not configured — webhook REJETÉ (fail-closed).');

            return null;
        }

        // Strip the "sha256=" prefix if present (#6561 — l'ancien
        // ltrim($header, 'sha256=') masquait des caractères du début de la
        // signature ; seul le substr explicite est conservé).
        $provided = str_starts_with($signatureHeader, 'sha256=')
            ? substr($signatureHeader, 7)
            : $signatureHeader;

        if (empty($provided)) {
            Log::warning('Chargily: Missing or malformed signature header.');
            return null;
        }

        $expected = hash_hmac('sha256', $payload, $this->webhookSecret);

        if (!hash_equals($expected, $provided)) {
            Log::warning('Chargily: Webhook signature mismatch.');
            return null;
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            Log::warning('Chargily: Invalid JSON payload.');
            return null;
        }

        return $data;
    }
}
