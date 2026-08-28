<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\CRM\Domain\Contracts\CampaignConsentCheckerInterface;
use App\Modules\CRM\Domain\Contracts\EmailProviderInterface;
use App\Modules\CRM\Domain\DTOs\EmailDeliveryResult;
use App\Modules\CRM\Domain\DTOs\EmailMessage;
use App\Modules\CRM\Domain\Exceptions\EmailRateLimitExceededException;
use App\Modules\CRM\Domain\Models\CrmCampaignSend;
use App\Modules\CRM\Infrastructure\Services\EmailRateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Canal email CRM — Issue #5726.
 *
 * Règles :
 *   - fournisseur interchangeable (`EmailProviderInterface`, config
 *     `crm.email.provider` — log par défaut, mail via Laravel) ;
 *   - aucun message marketing sans consentement requis
 *     (`CampaignConsentCheckerInterface`, fail-closed #5724) ;
 *   - suppression (bounce/complaint/désabonnement) respectée avant tout
 *     envoi — l'adresse n'est stockée qu'en hash SHA-256 (aucune PII) ;
 *   - quotas par tenant/heure (marketing vs transactionnel) → 429 ;
 *   - chaque envoi/événement est audité (`audit_logs`, module crm-email)
 *     et journalisé dans `crm_email_events`.
 */
final class CrmEmailService
{
    public function __construct(
        private readonly EmailProviderInterface $provider,
        private readonly EmailRateLimiter $rateLimiter,
        private readonly CampaignConsentCheckerInterface $consentChecker,
    ) {}

    public function sendTransactional(EmailMessage $message, string $companyId): EmailDeliveryResult
    {
        $email = $this->normalizeEmail($message->to);

        if ($this->isSuppressed($companyId, $email)) {
            $this->audit($companyId, 'email.suppressed', ['reason' => 'suppression', 'email_hash' => $this->hash($email)]);

            return EmailDeliveryResult::suppressed('suppressed');
        }

        $configuredLimit = config('crm.email.transactional_rate_limit_per_hour', 2000);
        $limit = is_numeric($configuredLimit) ? (int) $configuredLimit : 2000;
        if (! $this->rateLimiter->consume($companyId, 'transactional', $limit)) {
            throw new EmailRateLimitExceededException('Transactional email quota exceeded.');
        }

        $result = $this->provider->send($message);
        $this->recordSend($companyId, $message, $result);

        return $result;
    }

    public function sendMarketing(EmailMessage $message, string $companyId, int $contactId): EmailDeliveryResult
    {
        // Consentement marketing email REQUIS — fail-closed.
        if (! $this->consentChecker->allows($contactId, 'email')) {
            throw ValidationException::withMessages(['contact' => 'Consentement email marketing absent pour ce contact.']);
        }

        $email = $this->normalizeEmail($message->to);

        if ($this->isSuppressed($companyId, $email)) {
            $this->audit($companyId, 'email.suppressed', ['reason' => 'suppression', 'email_hash' => $this->hash($email)]);

            return EmailDeliveryResult::suppressed('suppressed');
        }

        $configuredLimit = config('crm.email.rate_limit_per_hour', 500);
        $limit = is_numeric($configuredLimit) ? (int) $configuredLimit : 500;
        if (! $this->rateLimiter->consume($companyId, 'marketing', $limit)) {
            throw new EmailRateLimitExceededException('Marketing email quota exceeded.');
        }

        $result = $this->provider->send($message);
        $this->recordSend($companyId, $message, $result);

        return $result;
    }

    /**
     * Envoi d'un envoi de campagne en attente (#5724) — le canal email prend
     * en charge `crm_campaign_sends`. L'adresse du contact est résolue depuis
     * `crm_contacts` (#5708) ; tant que la table n'existe pas, l'envoi est
     * marqué failed avec un message explicite (jamais de crash).
     */
    public function sendCampaignSend(CrmCampaignSend $send, string $companyId): EmailDeliveryResult
    {
        if (! Schema::hasTable('crm_contacts')) {
            return EmailDeliveryResult::failed('CRM contacts not migrated yet (#5708)');
        }

        $contact = DB::table('crm_contacts')
            ->where('company_id', $companyId)
            ->where('id', $send->contact_id)
            ->first();

        if ($contact === null || ! is_string($contact->email ?? null) || $contact->email === '') {
            return EmailDeliveryResult::failed('contact not found or without email');
        }

        $message = new EmailMessage(
            $contact->email,
            'Campagne CRM '.$send->campaign_id,
            'Message de campagne (canal email).',
            ['contact_id' => $send->contact_id, 'campaign_send_id' => $send->id],
        );

        $result = $this->sendTransactional($message, $companyId);

        if ($result->messageId !== null) {
            $send->update([
                'status' => 'sent',
                'provider_message_id' => $result->messageId,
                'sent_at' => now(),
            ]);
        } elseif ($result->status === 'suppressed') {
            $send->update(['status' => 'suppressed']);
        } else {
            $send->update(['status' => 'failed', 'error' => $result->error]);
        }

        return $result;
    }

    public function isSuppressed(string $companyId, string $email): bool
    {
        return DB::table('crm_email_suppressions')
            ->where('company_id', $companyId)
            ->where('email_hash', $this->hash($this->normalizeEmail($email)))
            ->exists();
    }

    public function suppress(string $companyId, string $email, string $reason, ?string $source = null): void
    {
        $normalized = $this->normalizeEmail($email);

        DB::table('crm_email_suppressions')
            ->updateOrInsert(
                ['company_id' => $companyId, 'email_hash' => $this->hash($normalized)],
                ['reason' => $reason, 'source' => $source, 'created_at' => now(), 'updated_at' => now()],
            );

        $this->audit($companyId, 'email.suppression_added', [
            'reason' => $reason,
            'email_hash' => $this->hash($normalized),
        ]);
    }

    /**
     * Désabonnement (lien email) : suppression + retrait du consentement
     * marketing email si la table de consentements existe (#5722).
     */
    public function unsubscribe(string $companyId, int $contactId, string $email): void
    {
        $normalized = $this->normalizeEmail($email);
        $hash = $this->hash($normalized);

        DB::table('crm_email_suppressions')
            ->updateOrInsert(
                ['company_id' => $companyId, 'email_hash' => $hash],
                ['contact_id' => $contactId, 'reason' => 'unsubscribe', 'source' => 'email_link', 'created_at' => now(), 'updated_at' => now()],
            );

        if (Schema::hasTable('crm_consents')) {
            DB::table('crm_consents')
                ->where('company_id', $companyId)
                ->where('contact_id', $contactId)
                ->where('channel', 'email')
                ->where('purpose', 'marketing')
                ->update([
                    'status' => 'withdrawn',
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $this->audit($companyId, 'email.unsubscribed', [
            'contact_id' => $contactId,
            'email_hash' => $hash,
        ]);
    }

    /**
     * Événement provider (webhook signé) : journalisé ; bounce/complaint/
     * unsubscribed → suppression + propagation aux envois de campagne.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhookEvent(array $payload): void
    {
        /** @var string $companyId */
        $companyId = $payload['company_id'];
        /** @var string $event */
        $event = $payload['event'];
        /** @var string|null $messageId */
        $messageId = $payload['message_id'] ?? null;
        /** @var string|null $email */
        $email = isset($payload['email']) && is_string($payload['email']) ? $payload['email'] : null;
        $sendId = null;
        if (isset($payload['send_id']) && is_numeric($payload['send_id'])) {
            $sendId = (int) $payload['send_id'];
        }

        DB::table('crm_email_events')->insert([
            'company_id' => $companyId,
            'send_id' => $sendId,
            'provider_message_id' => $messageId,
            'event' => $event,
            'payload' => json_encode($payload) ?: '{}',
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (in_array($event, ['bounced', 'complained', 'unsubscribed'], true)) {
            if ($email !== null) {
                $this->suppress($companyId, $email, $event, 'webhook');
            }

            if ($messageId !== null && Schema::hasTable('crm_campaign_sends')) {
                $status = $event === 'unsubscribed' ? 'suppressed' : ($event === 'bounced' ? 'bounced' : 'failed');
                DB::table('crm_campaign_sends')
                    ->where('company_id', $companyId)
                    ->where('provider_message_id', $messageId)
                    ->update(['status' => $status, 'updated_at' => now()]);
            }
        }
    }

    public function providerName(): string
    {
        return $this->provider->providerName();
    }

    private function recordSend(string $companyId, EmailMessage $message, EmailDeliveryResult $result): void
    {
        $this->audit($companyId, $result->isDelivered() ? 'email.sent' : 'email.failed', [
            'to_hash' => $this->hash($this->normalizeEmail($message->to)),
            'subject' => $message->subject,
            'provider' => $this->provider->providerName(),
            'message_id' => $result->messageId,
            'error' => $result->error,
            'context' => $message->context,
        ]);
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function hash(string $email): string
    {
        return hash('sha256', $this->normalizeEmail($email));
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    private function audit(string $companyId, string $action, array $newValues): void
    {
        $userId = Auth::guard('sanctum')->id();

        AuditLog::create([
            'company_id' => $companyId,
            'user_id' => $userId !== null ? (int) $userId : null,
            'action' => $action,
            'module' => 'crm-email',
            'auditable_type' => null,
            'auditable_id' => null,
            'new_values' => $newValues,
        ]);
    }
}
