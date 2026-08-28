<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Contracts\ChannelAdapterContract;
use App\Modules\CRM\Domain\Contracts\CrmChannelMessageRepositoryInterface;
use App\Modules\CRM\Domain\Enums\CrmMessageStatus;
use App\Modules\CRM\Domain\Exceptions\CrmChannelNotFoundException;
use App\Modules\CRM\Domain\Exceptions\CrmConsentRequiredException;
use App\Modules\CRM\Domain\Exceptions\CrmProviderException;
use App\Modules\CRM\Domain\Models\CrmChannel;
use App\Modules\CRM\Domain\Models\CrmChannelConversation;
use App\Modules\CRM\Domain\Models\CrmChannelMessage;
use App\Modules\CRM\Infrastructure\Jobs\RetryCrmMessageJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrateur des canaux de communication CRM (issue #5725/#5727).
 *
 * Envoi : normalisation → consentement → quota → adaptateur provider →
 * persistance → audit. Échec fournisseur retryable → job de retry borné ;
 * échec définitif ou tentatives épuisées → dead-letter (aucun retry infini).
 *
 * Réception (webhook) : résolution du tenant par channel, dédup par
 * (company_id, provider_message_id), inbox unique par conversation,
 * mises à jour de statut de livraison idempotentes.
 */
final class CrmChannelService
{
    /**
     * @param  array<string, ChannelAdapterContract>  $adapters  type → adaptateur
     */
    public function __construct(
        private readonly array $adapters,
        private readonly CrmChannelMessageRepositoryInterface $messages,
        private readonly CrmConsentGuard $consentGuard,
        private readonly CrmQuotaService $quotaService,
    ) {}

    /**
     * Envoie un message via le canal du tenant courant.
     *
     * @param  array<string, mixed>  $options  contact_id, purpose, template_parameters…
     * @return CrmChannelMessage
     *
     * @throws CrmChannelNotFoundException
     * @throws CrmConsentRequiredException
     * @throws CrmProviderException
     */
    public function send(CrmChannel $channel, string $toAddress, ?string $body, ?string $templateName, array $options = []): CrmChannelMessage
    {
        $adapter = $this->adapterFor($channel->type);
        $normalized = $adapter->normalize($toAddress);
        if ($normalized === null) {
            throw new CrmProviderException('Adresse de destination invalide pour le canal '.$channel->type, false, 'INVALID_ADDRESS');
        }

        $contactId = isset($options['contact_id']) && is_string($options['contact_id']) ? $options['contact_id'] : null;
        $purpose = isset($options['purpose']) && is_string($options['purpose']) ? $options['purpose'] : 'transactional';
        $this->consentGuard->assertConsent($contactId, $channel->type, $purpose);
        $this->quotaService->assertQuotaAvailable($channel);

        $settings = is_array($channel->settings) ? $channel->settings : [];
        if (is_array($options['template_parameters'] ?? null)) {
            $settings['template_parameters'] = $options['template_parameters'];
        }

        $message = $this->messages->createOutbound(
            $channel->id,
            $channel->provider,
            $normalized,
            $body,
            $templateName,
            (int) config('crm.channels.max_attempts', 3),
        );

        try {
            $result = $adapter->send($normalized, $body, $templateName, $settings);
            $this->messages->markSent($message->id, (string) $result['provider_message_id'], isset($result['cost']) ? (float) $result['cost'] : null);
            $this->quotaService->recordUsage($channel->refresh());

            Log::info('CRM channel: message envoyé', [
                'channel_id' => $channel->id,
                'type' => $channel->type,
                'provider_message_id' => $result['provider_message_id'],
            ]);

            return $message->refresh();
        } catch (CrmProviderException $e) {
            $this->handleProviderFailure($message, $e, $channel);

            throw $e;
        }
    }

    /**
     * Traite un événement de webhook entrant (WhatsApp Business, issue #5725).
     *
     * @param  array<string, mixed>  $entry  entry.value (messages + statuses)
     */
    public function handleInbound(array $entry, string $provider): void
    {
        $changes = is_array($entry['changes'] ?? null) ? $entry['changes'] : [];
        foreach ($changes as $change) {
            $value = is_array($change['value'] ?? null) ? $change['value'] : [];
            $this->handleInboundMessages($value, $provider);
            $this->handleInboundStatuses($value, $provider);
        }
    }

    /**
     * Retente un message en dead-letter potentiel (job de retry borné).
     */
    public function retry(string $messageId): void
    {
        $message = CrmChannelMessage::query()->find($messageId);
        if ($message === null || $message->status !== CrmMessageStatus::FAILED) {
            return;
        }

        $channel = $message->channel()->first();
        if ($channel === null) {
            $this->messages->deadLetter($message->id, 'CHANNEL_GONE', 'Canal supprimé pendant le retry.');

            return;
        }

        $adapter = $this->adapterFor($channel->type);
        $settings = is_array($channel->settings) ? $channel->settings : [];

        try {
            $result = $adapter->send(
                (string) $message->to_address,
                $message->body,
                $message->template_name,
                $settings,
            );
            $this->messages->markSent($message->id, (string) $result['provider_message_id'], isset($result['cost']) ? (float) $result['cost'] : null);
            $this->quotaService->recordUsage($channel->refresh());
        } catch (CrmProviderException $e) {
            $this->handleProviderFailure($message->refresh(), $e, $channel);
        }
    }

    // ── Webhook : messages entrants ─────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $value
     */
    private function handleInboundMessages(array $value, string $provider): void
    {
        $phoneNumberId = is_string($value['metadata']['phone_number_id'] ?? null) ? (string) $value['metadata']['phone_number_id'] : '';
        $channel = $this->resolveChannelByProviderId($phoneNumberId);
        if ($channel === null) {
            Log::warning('CRM webhook: channel introuvable pour phone_number_id (ignoré)', [
                'phone_number_id' => $phoneNumberId,
            ]);

            return;
        }

        $messages = is_array($value['messages'] ?? null) ? $value['messages'] : [];
        foreach ($messages as $message) {
            $providerMessageId = is_string($message['id'] ?? null) ? (string) $message['id'] : '';
            if ($providerMessageId === '') {
                continue;
            }

            DB::transaction(function () use ($channel, $provider, $providerMessageId, $message): void {
                $existing = $this->messages->findByProviderMessageId((string) currentCompany()->id, $providerMessageId);
                if ($existing !== null) {
                    // Rejeu webhook → acquittement silencieux (idempotence).
                    return;
                }

                $from = is_string($message['from'] ?? null) ? (string) $message['from'] : null;
                $body = $this->extractTextBody($message);
                $conversation = $this->resolveConversation($channel, $providerMessageId, $from);
                $this->messages->createInbound($channel->id, $conversation?->id, $provider, $providerMessageId, $from, $body);
                $this->touchConversation($conversation);
            });
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function handleInboundStatuses(array $value, string $provider): void
    {
        $statuses = is_array($value['statuses'] ?? null) ? $value['statuses'] : [];
        foreach ($statuses as $status) {
            $providerMessageId = is_string($status['id'] ?? null) ? (string) $status['id'] : '';
            $statusName = is_string($status['status'] ?? null) ? (string) $status['status'] : '';
            if ($providerMessageId === '' || $statusName === '') {
                continue;
            }

            $mapped = $this->mapDeliveryStatus($statusName);
            if ($mapped === null) {
                continue;
            }

            $this->messages->applyDeliveryStatus((string) currentCompany()->id, $providerMessageId, $mapped, Carbon::now());
        }
    }

    private function mapDeliveryStatus(string $providerStatus): ?string
    {
        return match ($providerStatus) {
            'sent' => CrmMessageStatus::SENT,
            'delivered' => CrmMessageStatus::DELIVERED,
            'read' => CrmMessageStatus::READ,
            'failed' => CrmMessageStatus::FAILED,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function extractTextBody(array $message): ?string
    {
        $type = $message['type'] ?? 'text';
        $body = $message[$type]['body'] ?? $message[$type]['text'] ?? null;

        return is_string($body) ? $body : null;
    }

    private function resolveChannelByProviderId(string $phoneNumberId): ?CrmChannel
    {
        if ($phoneNumberId === '') {
            return null;
        }

        // La résolution se fait par settings.phone_number_id — le tenant est
        // déduit du scope BelongsToCompany (search_path) + filtre explicite.
        return CrmChannel::query()
            ->where('type', 'whatsapp')
            ->where('is_configured', true)
            ->get()
            ->first(static fn (CrmChannel $channel): bool => is_string($channel->settings['phone_number_id'] ?? null)
                && $channel->settings['phone_number_id'] === $phoneNumberId);
    }

    private function resolveConversation(CrmChannel $channel, string $providerMessageId, ?string $from): ?CrmChannelConversation
    {
        // Inbox unique : une conversation par correspondant (numéro client).
        if ($from === null || $from === '') {
            return null;
        }

        $conversation = CrmChannelConversation::query()
            ->where('channel_id', $channel->id)
            ->where('provider_conversation_id', $this->conversationKey($channel->id, $from))
            ->first();

        if ($conversation === null) {
            $conversation = CrmChannelConversation::query()->create([
                'channel_id' => $channel->id,
                'provider_conversation_id' => $this->conversationKey($channel->id, $from),
                'status' => 'open',
            ]);
        }

        return $conversation;
    }

    private function touchConversation(?CrmChannelConversation $conversation): void
    {
        if ($conversation === null) {
            return;
        }

        $conversation->forceFill([
            'last_message_at' => Carbon::now(),
            'unread_count' => $conversation->unread_count + 1,
        ])->save();
    }

    private function conversationKey(string $channelId, string $from): string
    {
        // Hash déterministe (PII : le numéro du client ne doit jamais vivre
        // en clair dans une colonne — convention CRM #5713).
        return substr(hash('sha256', $channelId.'|'.$from), 0, 64);
    }

    // ── Gestion des échecs provider ─────────────────────────────────────────

    private function handleProviderFailure(CrmChannelMessage $message, CrmProviderException $e, CrmChannel $channel): void
    {
        $message = $this->messages->incrementAttempts($message->id);

        if (! $e->isRetryable() || $message->attempts >= $message->max_attempts) {
            $this->messages->deadLetter($message->id, $e->providerErrorCode(), substr($e->getMessage(), 0, 500));
            $this->markChannelError($channel, $e->getMessage());
            Log::error('CRM channel: message dead-lettered (tentatives épuisées ou erreur définitive)', [
                'message_id' => $message->id,
                'channel_id' => $channel->id,
                'error_code' => $e->providerErrorCode(),
            ]);

            return;
        }

        $this->messages->markFailedRetryable($message->id, $e->providerErrorCode(), substr($e->getMessage(), 0, 500));
        $this->markChannelError($channel, $e->getMessage());

        RetryCrmMessageJob::dispatch($message->id)
            ->delay(Carbon::now()->addSeconds((int) config('crm.channels.retry_backoff_seconds', 60)))
            ->onQueue((string) config('crm.channels.queue', 'default'));
    }

    private function markChannelError(CrmChannel $channel, string $errorMessage): void
    {
        $channel->forceFill([
            'status' => 'error',
            'last_error_message' => substr($errorMessage, 0, 254),
            'last_error_at' => Carbon::now(),
        ])->save();
    }

    private function adapterFor(string $type): ChannelAdapterContract
    {
        if (! isset($this->adapters[$type])) {
            throw new CrmProviderException('Aucun adaptateur enregistré pour le canal '.$type, false, 'NO_ADAPTER');
        }

        return $this->adapters[$type];
    }
}
