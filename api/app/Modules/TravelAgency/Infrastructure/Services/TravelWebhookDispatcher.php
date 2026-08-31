<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Enums\TravelWebhookDeliveryStatus;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookDelivery;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * TRAVEL-806 (#6097) — Dispatcher des webhooks sortants transporteurs.
 *
 * - Signature HMAC-SHA256 du body brut : en-tête `X-Leopardo-Signature: sha256=…`.
 * - Idempotence : une livraison par (subscription, événement) — rejeu sans doublon.
 * - Retries : backoff exponentiel borné, dead-letter après MAX_ATTEMPTS.
 * - Le payload livré est redigé (jamais de secret, jamais de PII en clair).
 */
final class TravelWebhookDispatcher
{
    public const MAX_ATTEMPTS = 5;

    public const SIGNATURE_HEADER = 'X-Leopardo-Signature';

    public function signature(string $payload, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $payload, $secret);
    }

    /** Envoie une livraison et met à jour son statut. Retourne true si livrée. */
    public function deliver(TravelWebhookDelivery $delivery): bool
    {
        if ($delivery->status === TravelWebhookDeliveryStatus::DEAD) {
            return false;
        }

        $subscription = $delivery->subscription;
        if (! $subscription instanceof TravelWebhookSubscription) {
            $delivery->forceFill([
                'status' => TravelWebhookDeliveryStatus::DEAD,
                'last_error' => 'Abonnement introuvable',
            ])->save();

            return false;
        }

        $payload = $this->redactedPayload($delivery->payload_redacted ?? []);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

        try {
            $response = $this->client($subscription)->send('POST', $subscription->url, [
                'body' => $body,
                'headers' => [
                    'Content-Type' => 'application/json',
                    self::SIGNATURE_HEADER => $this->signature($body, $subscription->secret()),
                    'X-Leopardo-Event' => $delivery->event_type,
                    'X-Leopardo-Delivery-Id' => $delivery->id,
                ],
                'timeout' => 8,
            ]);

            if ($response->successful()) {
                $delivery->forceFill([
                    'status' => TravelWebhookDeliveryStatus::SENT,
                    'attempts' => $delivery->attempts + 1,
                    'last_status_code' => $response->status(),
                    'last_error' => null,
                    'next_attempt_at' => null,
                    'delivered_at' => now(),
                ])->save();

                return true;
            }

            $this->markFailed($delivery, (int) $response->status(), 'HTTP '.$response->status());

            return false;
        } catch (ConnectionException|Throwable $exception) {
            $this->markFailed($delivery, null, $this->truncateError($exception->getMessage()));

            return false;
        }
    }

    private function markFailed(TravelWebhookDelivery $delivery, ?int $statusCode, string $error): void
    {
        $attempts = $delivery->attempts + 1;
        $dead = $attempts >= self::MAX_ATTEMPTS;

        $delivery->forceFill([
            'status' => $dead ? TravelWebhookDeliveryStatus::DEAD : TravelWebhookDeliveryStatus::FAILED,
            'attempts' => $attempts,
            'last_status_code' => $statusCode,
            'last_error' => $error,
        ])->save();

        if ($dead) {
            Log::warning('travel.webhook.dead', [
                'delivery_id' => $delivery->id,
                'event_type' => $delivery->event_type,
                'error' => $error,
            ]);

            return;
        }

        $delivery->scheduleRetry();
        $delivery->save();
    }

    private function client(TravelWebhookSubscription $subscription): PendingRequest
    {
        return Http::withoutVerifying()
            ->acceptJson()
            ->retry(0); // retries gérés par le journal de livraison (backoff)
    }

    /** @param array<string, mixed> $payload */
    private function redactedPayload(array $payload): array
    {
        $redacted = [];
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $redacted[$key] = $this->redactedPayload($value);
                continue;
            }
            if (in_array($key, ['secret', 'token', 'password', 'validation_code', 'document_number'], true)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }
            $redacted[$key] = $value;
        }

        return $redacted;
    }

    private function truncateError(string $message): string
    {
        return mb_substr($message, 0, 500);
    }
}
