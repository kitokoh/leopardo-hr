<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchWebhook implements ShouldQueue
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

    public function handle(): void
    {
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
                    'Webhook-Id' => \Illuminate\Support\Str::uuid()->toString(),
                    'Webhook-Timestamp' => (string) $timestamp,
                    'Webhook-Signature' => $svixSignature,
                    'X-Leopardo-Event' => $this->event, // Keep for legacy
                    'X-Leopardo-Signature' => $signature, // Keep for legacy
                    'Content-Type' => 'application/json',
                ])
                ->post($this->endpoint->url, $body);

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
                $this->endpoint->update([
                    'failure_count' => 0,
                    'last_triggered_at' => now(),
                ]);
            } else {
                $this->endpoint->increment('failure_count');
                if ($this->endpoint->failure_count >= 10) {
                    $this->endpoint->update(['active' => false]);
                }
            }
        } catch (\Throwable $e) {
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
    }
}
