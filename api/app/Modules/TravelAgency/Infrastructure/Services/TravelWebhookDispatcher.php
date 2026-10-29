<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Contracts\Queue\OutboundWebhookSigner;
use App\Modules\TravelAgency\Domain\Enums\TravelWebhookDeliveryStatus;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookDelivery;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;
use App\Rules\NotPrivateUrl;
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
 *
 * BOS-021 (#8210) — ce dispatcher était le dernier producteur à recopier sa
 * propre primitive HMAC (`hash_hmac` en dur) au lieu de passer par le contrat
 * `OutboundWebhookSigner`. Il consomme désormais le socle commun, comme
 * Billing (`WebhookEnvelopeBuilder`) et Platform (`DispatchWebhook`).
 *
 * La chaîne signée reste PROPRE à Travel (body brut, sans préfixe
 * d'horodatage) : c'est le contrat public `X-Leopardo-Signature` que les
 * transporteurs vérifient déjà. Unifier la primitive sans unifier la chaîne
 * signée est ce qui permet de dédupliquer le code sans casser un seul
 * consommateur — les octets émis sont identiques (prouvé par
 * `OutboundWebhookSignatureUnificationTest`).
 */
final class TravelWebhookDispatcher
{
    public const MAX_ATTEMPTS = 5;

    public const SIGNATURE_HEADER = 'X-Leopardo-Signature';

    public function __construct(
        private readonly OutboundWebhookSigner $signer,
    ) {}

    public function signature(string $payload, string $secret): string
    {
        return 'sha256='.$this->signer->sign($secret, $payload);
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

        // Anti-SSRF, défense en profondeur (même garde que `DispatchWebhook`
        // côté Billing) : l'URL était validée à la création de l'abonnement,
        // mais le DNS peut être re-lié entre-temps. On re-résout juste avant
        // l'envoi au lieu de faire confiance à la valeur stockée.
        $host = parse_url((string) $subscription->url, PHP_URL_HOST);
        if (! str_starts_with((string) $subscription->url, 'https://')
            || ! is_string($host)
            || ! NotPrivateUrl::isPublicHost($host)) {
            $delivery->forceFill([
                'status' => TravelWebhookDeliveryStatus::DEAD,
                'last_error' => 'URL de livraison refusée (hôte privé, réservé ou non HTTPS)',
            ])->save();

            Log::warning('travel.webhook.blocked_host', [
                'delivery_id' => $delivery->id,
                'subscription_id' => $subscription->id,
            ]);

            return false;
        }

        $payload = $this->redactedPayload($delivery->payload_redacted ?? []);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

        try {
            $response = $this->client($subscription)->send('POST', $subscription->url, [
                'body' => $body,
                'headers' => [
                    'Content-Type' => 'application/json',
                    self::SIGNATURE_HEADER => $this->signature($body, app(TravelWebhookSecretService::class)->get($subscription)),
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
        } catch (Throwable $exception) {
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
        // #8210 : `withoutVerifying()` désactivait la vérification TLS sur
        // CHAQUE livraison webhook transporteur — un homme du milieu pouvait
        // lire et réécrire les billets, les montants et les coordonnées des
        // passagers. La vérification est désormais active : l'URL est déjà
        // contrainte à https:// par la garde anti-SSRF ci-dessus.
        return Http::acceptJson()
            ->retry(0); // retries gérés par le journal de livraison (backoff)
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
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
