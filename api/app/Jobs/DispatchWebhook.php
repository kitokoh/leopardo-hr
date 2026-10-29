<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Queue\OutboundWebhookTransport;
use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Billing\Domain\Models\WebhookDelivery;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use App\Modules\Billing\Infrastructure\Services\WebhookEnvelopeBuilder;
use App\Shared\Exceptions\OutboundWebhookBlockedException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
        private readonly int $eventVersion = WebhookEnvelopeBuilder::CURRENT_VERSION,
        private readonly ?string $correlationId = null,
        private readonly ?string $occurredAt = null,
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
        $body = WebhookEnvelopeBuilder::build(
            $this->event,
            $this->eventVersion,
            $this->endpoint->company_id,
            $this->correlationId ?? (string) Str::uuid(),
            $this->occurredAt ?? now()->toIso8601String(),
            $this->payload,
        );

        WebhookDelivery::create([
            'webhook_endpoint_id' => $this->endpoint->id,
            'event' => $this->event,
            'payload' => $body,
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
        // Issue #5744 : enveloppe canonique versionnée (additive) — voir
        // WebhookEnvelopeBuilder et docs/api/VERSIONING.md § 5.
        $body = WebhookEnvelopeBuilder::build(
            $this->event,
            $this->eventVersion,
            $this->endpoint->company_id,
            $this->correlationId ?? (string) Str::uuid(),
            $this->occurredAt ?? now()->toIso8601String(),
            $this->payload,
        );

        $timestamp = time();

        // BOS-021 (#8210) — la livraison HTTP et la garde anti-SSRF viennent
        // du transport partagé (BC-14) au lieu d'être recopiées ici. La garde
        // reste appliquée AVANT l'appel (re-résolution DNS, TOCTOU) : elle a
        // simplement déménagé dans le socle, avec le même comportement.
        //
        // Le corps est sérialisé UNE fois et la même chaîne sert à signer et à
        // envoyer : deux `json_encode` successifs produiraient le même octet
        // aujourd'hui, mais un seul point de sérialisation rend la propriété
        // structurelle plutôt que dépendante de l'ordre des clés.
        $rawBody = json_encode($body, JSON_THROW_ON_ERROR);

        try {
            $response = app(OutboundWebhookTransport::class)->send(
                (string) $this->endpoint->url,
                $rawBody,
                WebhookEnvelopeBuilder::headers(
                    $this->event,
                    (string) $this->eventVersion,
                    (string) $this->endpoint->secret,
                    $body,
                    $timestamp,
                ),
            );
        } catch (OutboundWebhookBlockedException $e) {
            // Décision, pas incident : l'endpoint ne redeviendra pas public
            // tout seul, on le désactive et on n'insiste pas.
            Log::warning('Webhook delivery blocked: URL resolves to a disallowed host', [
                'endpoint_id' => $this->endpoint->id,
                'event' => $this->event,
            ]);

            $this->endpoint->update(['active' => false]);

            return;
        } catch (Throwable $e) {
            WebhookDelivery::create([
                'webhook_endpoint_id' => $this->endpoint->id,
                'event' => $this->event,
                'payload' => $body,
                'response_code' => 0,
                'response_body' => mb_substr($e->getMessage(), 0, 2000),
                'duration_ms' => 0,
            ]);

            $this->endpoint->increment('failure_count');

            Log::warning('Webhook delivery failed', [
                'endpoint_id' => $this->endpoint->id,
                'event' => $this->event,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        WebhookDelivery::create([
            'webhook_endpoint_id' => $this->endpoint->id,
            'event' => $this->event,
            'payload' => $body,
            'response_code' => $response->status,
            'response_body' => $response->body,
            'duration_ms' => $response->durationMs,
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
            sprintf('Webhook delivery failed with status %d: %s', $response->status, mb_substr($response->body, 0, 500))
        );
    }
}
