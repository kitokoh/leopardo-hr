<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Application\Services\CrmEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook des événements email provider — Issue #5726.
 *
 * Endpoint NON authentifié (appelé par le fournisseur) mais protégé par un
 * secret partagé (`X-Leopardo-Webhook-Secret` == CRM_EMAIL_WEBHOOK_SECRET).
 * Gère bounce/complaint/unsubscribe (suppression + propagation aux envois
 * de campagne) et journalise les événements dans `crm_email_events`.
 */
class CrmEmailWebhookController extends Controller
{
    public function __construct(private readonly CrmEmailService $emails) {}

    public function handle(Request $request): JsonResponse
    {
        $expectedSecret = (string) config('crm.email.webhook_secret', '');

        if ($expectedSecret === ''
            || ! hash_equals($expectedSecret, (string) $request->header('X-Leopardo-Webhook-Secret', ''))) {
            return response()->json(['error' => 'INVALID_WEBHOOK_SECRET'], 403);
        }

        $payload = $request->validate([
            'company_id' => ['required', 'string'],
            'event' => ['required', 'string', 'in:sent,delivered,bounced,complained,opened,clicked,unsubscribed'],
            'message_id' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'send_id' => ['nullable', 'integer'],
        ]);

        $this->emails->handleWebhookEvent($payload);

        return response()->json(['data' => ['received' => true]]);
    }
}
