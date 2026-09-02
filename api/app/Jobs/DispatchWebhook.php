<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Billing\Domain\Models\WebhookDelivery;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use App\Rules\NotPrivateUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DispatchWebhook implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    public function __construct(
        private readonly WebhookEndpoint $endpoint,
        private readonly string $event,
        private readonly array $payload,
    ) {
        $this->queue = 'webhooks';
    }

    /**
     * Called by the queue worker once every retry attempt (see `$tries`)
     * has been exhausted for this job.
     *
     * Records a dedicated dead-letter `WebhookDelivery` row — distinct from
     * the generic `failed_jobs` entry Laravel already writes — so partner
     * admins can see and replay it from `GET /webhooks/{id}/dead-letters`
     * without needing shell/DB access. See PA2-API-006.
     */
    public function failed(Throwable $exception): void
    {
        WebhookDelivery::create([
            'webhook_endpoint_id' => $this->endpoint->id,
            'event' => $this->event,
            'payload' => [
                'event' => $this->event,
                'timestamp' => now()->toIso8601String(),
                'data' => $this->payload,
            ],
            'response_code' => 0,
            'response_body' => mb_substr('All retries exhausted: '.$exception->getMessage(), 0, 2000),
            'duration_ms' => 0,
            'dead_lettered_at' => now(),
        ]);

        Log::error('Webhook delivery dead-lettered after exhausting all retries', [
            'endpoint_id' => $this->endpoint->id,
            'event' => $this->event,
            'error' => $exception->getMessage(),
        ]);
    }

    public function tenantCompanyId(): ?string
    {
        // `SerializesModels` re-hydrates `$endpoint` from the DB when the job
        // is picked up by the worker, going through `WebhookEndpoint`'s
        // `BelongsToCompany` global scope. Without an active tenant context
        // at that point, the scope is a no-op and the row still resolves
        // correctly here (its `company_id` column is read directly) — but
        // every model touched inside `handle()` below needs the real context
        // established first, hence the middleware.
        return $this->endpoint->company_id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function handle(): void
    {
        // Anti-SSRF, defence-in-depth: the URL was already validated against
        // private/reserved IP ranges when the endpoint was created/updated
        // (see StoreWebhookEndpointRequest/UpdateWebhookEndpointRequest), but
        // DNS can be rebound between then and now. Re-resolve and re-check
        // right before making the outbound request instead of trusting the
        // stored value blindly. See docs/security/AUDIT_API_2026-07-19.md.
        $host = parse_url($this->endpoint->url, PHP_URL_HOST);
        if (! str_starts_with($this->endpoint->url, 'https://') || ! is_string($host) || ! NotPrivateUrl::isPublicHost($host)) {
            Log::warning('Webhook delivery blocked: URL resolves to a disallowed host', [
                'endpoint_id' => $this->endpoint->id,
                'event' => $this->event,
            ]);

            $this->endpoint->update(['active' => false]);

            return;
        }

        $body = [
            'event' => $this->event,
            'timestamp' => now()->toIso8601String(),
            'data' => $this->payload,
        ];

        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$jsonBody}";
        $signature = hash_hmac('sha256', $signedPayload, $this->endpoint->secret);
        $svixSignature = "v1={$signature},t={$timestamp}";

        $start = microtime(true);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Webhook-Id' => Str::uuid()->toString(),
                    'Webhook-Timestamp' => (string) $timestamp,
                    'Webhook-Signature' => $svixSignature,
                    'X-Leopardo-Event' => $this->event, // Keep for legacy
                    'X-Leopardo-Signature' => $signature, // Keep for legacy
                    'Content-Type' => 'application/json',
                ])
                ->post($this->endpoint->url, $body);
        } catch (Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            WebhookDelivery::create([
                'webhook_endpoint_id' => $this->endpoint->id,
                'event' => $this->event,
                'payload' => $body,
                'response_code' => 0,
                'response_body' => mb_substr($e->getMessage(), 0, 2000),
                'duration_ms' => $durationMs,
            ]);

            $this->endpoint->increment('failure_count');

            Log::warning('Webhook delivery failed', [
                'endpoint_id' => $this->endpoint->id,
                'event' => $this->event,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        WebhookDelivery::create([
            'webhook_endpoint_id' => $this->endpoint->id,
            'event' => $this->event,
            'payload' => $body,
            'response_code' => $response->status(),
            'response_body' => mb_substr($response->body(), 0, 2000),
            'duration_ms' => $durationMs,
        ]);

        if ($response->successful()) {
            // #6550 (audit) : un succès réactive l'endpoint (la désactivation
            // n'est plus définitive — un aléa réseau ne tue plus le webhook
            // pour toujours).
            $this->endpoint->update([
                'active' => true,
                'failure_count' => 0,
                'last_triggered_at' => now(),
            ]);

            return;
        }

        // #6550 (audit) : un non-2xx doit être RETHROWN pour que le job
        // repasse par les retries (tries/backoff) puis par failed() →
        // dead-letter. Avant : la livraison était marquée réussie (aucun
        // retry, aucune dead-letter) — perte sèche. Le throw est HORS du
        // try/catch : le catch réseau (ci-dessus) n'est pas ré-exécuté, le
        // compteur d'échecs n'est pas incrémenté deux fois.
        $this->endpoint->increment('failure_count');
        if ($this->endpoint->failure_count >= 10) {
            $this->endpoint->update(['active' => false]);
        }

        throw new \RuntimeException(
            sprintf('Webhook delivery failed with status %d: %s', $response->status(), mb_substr($response->body(), 0, 500))
        );
    }
}
