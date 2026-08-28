<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\CRM\Domain\Exceptions\CrmWebhookSignatureInvalidException;
use App\Modules\CRM\Infrastructure\Services\CrmChannelService;
use App\Modules\CRM\Infrastructure\Services\CrmWebhookLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook entrant WhatsApp Business Cloud API (issue #5725).
 *
 * - GET : vérification d'abonnement Meta (hub.challenge) — fail-closed si
 *   verify_token non configuré.
 * - POST : signature X-Hub-Signature-256 (HMAC-SHA256, fail-closed), tenant
 *   résolu via le lookup public (phone_number_id → company), traitement sous
 *   TenantManager::withinTenant(), idempotence par
 *   (company_id, provider_message_id).
 *
 * Les erreurs de traitement sont journalisées + dead-letterées côté
 * messages ; le webhook acquitte 200 pour éviter les rejeux inutiles du
 * fournisseur (une signature invalide, elle, répond 401).
 */
class CrmWhatsAppWebhookController extends Controller
{
    public function __construct(
        private readonly CrmChannelService $channelService,
        private readonly CrmWebhookLookupService $lookupService,
        private readonly TenantManager $tenantManager,
    ) {}

    public function verify(Request $request): JsonResponse
    {
        $mode = (string) $request->query('hub_mode', '');
        $token = (string) $request->query('hub_verify_token', '');
        $challenge = (string) $request->query('hub_challenge', '');

        $expectedToken = (string) config('crm.webhooks.whatsapp_verify_token', '');

        if ($mode !== 'subscribe' || $expectedToken === '' || ! hash_equals($expectedToken, $token)) {
            return new JsonResponse(['error' => 'CRM_WEBHOOK_VERIFY_INVALID'], 403);
        }

        return new JsonResponse($challenge);
    }

    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('crm.webhooks.shared_secret', '');
        if ($secret === '') {
            Log::error('CRM webhook: shared_secret non configuré — webhook REFUSÉ (fail-closed). Set CRM_WEBHOOKS_SHARED_SECRET.');

            return new JsonResponse(['error' => 'CRM_WEBHOOK_NOT_CONFIGURED'], 503);
        }

        $raw = (string) $request->getContent();
        $signature = (string) $request->header('X-Hub-Signature-256', '');
        if (! $this->signatureIsValid($raw, $signature, $secret)) {
            throw new CrmWebhookSignatureInvalidException();
        }

        $payload = $request->json();
        $entries = is_array($payload) ? ($payload['entry'] ?? []) : [];

        foreach ($entries as $entry) {
            $this->processEntry(is_array($entry) ? $entry : []);
        }

        return new JsonResponse(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function processEntry(array $entry): void
    {
        $changes = is_array($entry['changes'] ?? null) ? $entry['changes'] : [];
        $phoneNumberId = $this->firstPhoneNumberId($changes);
        if ($phoneNumberId === null) {
            return;
        }

        $lookup = $this->lookupService->findByProviderKey('whatsapp', $phoneNumberId);
        if ($lookup === null) {
            Log::warning('CRM webhook: aucun lookup tenant pour phone_number_id', [
                'phone_number_id' => $phoneNumberId,
            ]);

            return;
        }

        $company = Company::query()->find($lookup->company_id);
        if ($company === null) {
            Log::error('CRM webhook: company introuvable pour lookup', [
                'company_id' => $lookup->company_id,
            ]);

            return;
        }

        $this->tenantManager->withinTenant($company, function () use ($entry): void {
            $this->channelService->handleInbound($entry, 'whatsapp');
        });
    }

    /**
     * @param  array<int, mixed>  $changes
     */
    private function firstPhoneNumberId(array $changes): ?string
    {
        foreach ($changes as $change) {
            $value = is_array($change['value'] ?? null) ? $change['value'] : [];
            $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
            if (is_string($phoneNumberId) && $phoneNumberId !== '') {
                return $phoneNumberId;
            }
        }

        return null;
    }

    private function signatureIsValid(string $rawBody, string $signatureHeader, string $secret): bool
    {
        if ($signatureHeader === '') {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signatureHeader);
    }
}
