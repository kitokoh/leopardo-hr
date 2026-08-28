<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Domain\Models\CommunicationEvent;
use App\Modules\Notification\Infrastructure\Services\EmployeeEmailLookupService;
use App\Modules\Platform\Infrastructure\Services\WebhookEventRegistry;
use App\Shared\Services\InboundWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PA2-COMM-007 - Inbound bounce/complaint notifications from the
 * configured email provider (Postmark, SES, Mailgun, ...). Public and
 * unauthenticated by nature (the provider is a third party, not a tenant),
 * protected by a shared secret instead of Sanctum — mirrors
 * `PaymentWebhookController`/`StripeWebhookController`.
 *
 * On a hard bounce or spam complaint, the recipient's `email_bounced_at` is
 * stamped so `MailMessageProvider` stops retrying that address on every
 * future communication, and a `communication_events` audit row is
 * recorded either way for observability.
 */
class EmailBounceWebhookController extends Controller
{
    public function __construct(
        private readonly EmployeeEmailLookupService $lookup,
        private readonly WebhookEventRegistry $registry,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $configuredSecret = (string) config('services.mail_bounce_webhook.secret', '');

        if ($configuredSecret === '') {
            // #2616 fail-closed : secret non configuré = webhook non
            // authentifiable = on REFUSE (503). Un webhook sans secret ne
            // doit jamais traiter un payload (fail-open = spooling/poisoning).
            Log::error('Email bounce webhook: secret non configuré — webhook REJETÉ (fail-closed).');

            abort(503, 'EMAIL_BOUNCE_WEBHOOK_NOT_CONFIGURED');
        }

        $providedSecret = (string) $request->header('X-Bounce-Webhook-Secret', '');

        if (! hash_equals($configuredSecret, $providedSecret)) {
            Log::warning('Email bounce webhook: invalid or missing shared secret');

            return new JsonResponse(['error' => 'Invalid signature'], 400);
        }

        // #5740 — frontière hostile : bornes d'entrée AVANT tout traitement
        // (l'idempotence est persistée ensuite, l'ordre begin → traitement
        // reste inchangé). Le secret partagé seul authentifie ; la taille et
        // la forme du payload sont bornées ; la fenêtre de rejeu est
        // optionnelle (providers legacy sans horodatage) mais vérifiée si
        // l'en-tête est présent.
        $rawPayload = $request->getContent();

        if (! InboundWebhookVerifier::payloadWithinLimit($rawPayload)) {
            Log::warning('Email bounce webhook: payload too large', ['bytes' => strlen($rawPayload)]);

            return new JsonResponse(['error' => 'Payload too large'], 413);
        }

        if ($request->isJson() && ! InboundWebhookVerifier::isJsonPayload($rawPayload)) {
            Log::warning('Email bounce webhook: invalid JSON payload');

            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $timestamp = InboundWebhookVerifier::timestampFromHeader($request->header('X-Webhook-Timestamp'));

        if ($timestamp !== null && ! InboundWebhookVerifier::timestampIsFresh($timestamp)) {
            Log::warning('Email bounce webhook: expired or skewed timestamp', ['timestamp' => $timestamp]);

            return new JsonResponse(['error' => 'Expired timestamp'], 400);
        }

        // #5444 — idempotence persistée : le registre clé (payload brut) sert de
        // verrou anti-rejeu AVANT tout traitement (begin → complete/release).
        $eventId = $this->registry->eventId($rawPayload);
        $replay = $this->registry->begin('email-bounce', $eventId, hash('sha256', $rawPayload));

        if ($replay !== null) {
            $this->registry->logReplay('email-bounce', $eventId, $replay['code']);

            return new JsonResponse(
                $this->registry->replayBody($replay['body'], ['received' => true, 'replayed' => true]),
                $replay['code'],
            );
        }

        /** @var array{email: string, event: string, reason?: string|null} $payload */
        $payload = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:320'],
            'event' => [
                'required',
                'string',
                'max:64',
                'in:bounce,hard_bounce,complaint,spam_complaint,delivered,opened,clicked,deferred',
            ],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $email = $payload['email'];
        $event = $payload['event'];
        $reason = $payload['reason'] ?? null;

        try {
            $employee = $this->lookup->resolve($email);

            if ($employee === null) {
                Log::info('Email bounce webhook: no matching employee for address', ['event' => $event]);

                $this->registry->complete('email-bounce', $eventId, 200, json_encode(['received' => true]) ?: '');

                return new JsonResponse(['received' => true]);
            }

            $isBounceOrComplaint = in_array($event, ['bounce', 'hard_bounce', 'complaint', 'spam_complaint'], true);

            if ($isBounceOrComplaint) {
                $employee->forceFill([
                    'email_bounced_at' => now(),
                    'email_bounce_reason' => $reason ?? $event,
                ])->save();
            }

            CommunicationEvent::query()->create([
                'company_id' => (string) $employee->company_id,
                'employee_id' => $employee->id,
                'event_name' => 'email_provider_webhook',
                'channel' => 'email',
                'status' => $isBounceOrComplaint ? 'bounced' : 'recorded',
                'provider' => (string) config('communication.providers.email', 'mail'),
                'metadata' => ['event' => $event],
                'error_message' => $reason,
                'occurred_at' => now(),
            ]);

            $this->registry->complete('email-bounce', $eventId, 200, json_encode(['received' => true]) ?: '');

            return new JsonResponse(['received' => true]);
        } catch (\Throwable $e) {
            $this->registry->release('email-bounce', $eventId);
            Log::error('Email bounce webhook: error handling event', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(['received' => false, 'error' => 'processing_error'], 500);
        }
    }
}
