<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Interfaces\Api\V1\Controllers;

use App\Modules\Paiements\Domain\DTOs\WebhookPayload;
use App\Modules\Paiements\Domain\Exceptions\InvalidWebhookSignatureException;
use App\Modules\Paiements\Domain\Models\PaymentWebhookEndpoint;
use App\Modules\Paiements\Infrastructure\Services\ProviderTypeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BC-38 PAIEMENTS (#8688) — webhooks entrants.
 *
 * Le routage se fait **par le token de l'endpoint**, jamais par parsing du
 * payload ni par le nom du fournisseur : un payload est controle par
 * l'emetteur, un token est un secret partage. C'est la seule facon d'identifier
 * l'instance sans ambiguite.
 *
 * Signature verifiee en fail-closed : sans secret configure, tout payload est
 * rejete. Un rejeu est un no-op (le rail est deterministe et l'idempotence
 * aval empeche tout doublon d'ecriture).
 */
class PaymentWebhookController
{
    public function __construct(private readonly ProviderTypeRegistry $registry) {}

    public function handle(Request $request, string $token): JsonResponse
    {
        $endpoint = PaymentWebhookEndpoint::query()
            ->where('token', $token)
            ->where('status', 'active')
            ->first();

        if ($endpoint === null) {
            return $this->error('webhook_endpoint_unknown', 'Endpoint de webhook inconnu.', 404);
        }

        $secret = $endpoint->secret_signature;
        $signature = (string) $request->header('X-Signature', '');

        if (! is_string($secret) || $secret === '') {
            return $this->error('webhook_secret_missing', 'Secret de signature non configure.', 400);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return $this->error('webhook_signature_invalid', 'Signature invalide.', 400);
        }

        $instance = $endpoint->providerInstance;

        if ($instance === null) {
            return $this->error('webhook_instance_missing', 'Instance de fournisseur introuvable.', 404);
        }

        $body = $request->all();

        $rail = $this->registry->make($instance->type_code);

        $result = $rail->traiterWebhook(new WebhookPayload(
            externalReference: (string) ($body['external_reference'] ?? ''),
            eventType: (string) ($body['event'] ?? 'unknown'),
            body: $body,
            config: $instance->config ?? [],
        ));

        return response()->json([
            'code' => 'ok',
            'message' => 'Webhook traite.',
            'data' => [
                'status' => $result->status->value,
                'external_reference' => $result->externalReference,
            ],
        ]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => null,
        ], $status);
    }
}
