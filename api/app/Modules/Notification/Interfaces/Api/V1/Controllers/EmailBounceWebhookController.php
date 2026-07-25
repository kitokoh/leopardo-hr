<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Domain\Models\CommunicationEvent;
use App\Modules\Notification\Infrastructure\Services\EmployeeEmailLookupService;
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
    public function __construct(private readonly EmployeeEmailLookupService $lookup) {}

    public function __invoke(Request $request): JsonResponse
    {
        $configuredSecret = (string) config('services.mail_bounce_webhook.secret', '');

        if ($configuredSecret !== '') {
            $providedSecret = (string) $request->header('X-Bounce-Webhook-Secret', '');

            if (! hash_equals($configuredSecret, $providedSecret)) {
                Log::warning('Email bounce webhook: invalid or missing shared secret');

                return new JsonResponse(['error' => 'Invalid signature'], 400);
            }
        }

        $email = (string) $request->input('email', '');
        $event = (string) $request->input('event', 'bounce');
        $reason = $request->input('reason');
        $reason = is_string($reason) ? substr($reason, 0, 255) : null;

        if ($email === '') {
            return new JsonResponse(['received' => true]);
        }

        $employee = $this->lookup->resolve($email);

        if ($employee === null) {
            Log::info('Email bounce webhook: no matching employee for address', ['event' => $event]);

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

        return new JsonResponse(['received' => true]);
    }
}
