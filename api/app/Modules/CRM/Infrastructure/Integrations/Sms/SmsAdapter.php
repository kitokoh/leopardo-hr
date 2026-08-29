<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Integrations\Sms;

use App\Modules\CRM\Domain\Contracts\ChannelAdapterContract;
use App\Modules\CRM\Domain\Enums\CrmChannelType;
use App\Modules\CRM\Infrastructure\Services\CrmPhoneNormalizer;
use Illuminate\Support\Facades\Log;

/**
 * Adaptateur SMS — provider AUDIT-ONLY (issue #5727).
 *
 * Conformément à la règle AGENTS.md (v4.16.122) : « SMS/WhatsApp restent en
 * provider audit-only tant qu'un fournisseur production, des signatures
 * webhook et des quotas par plan ne sont pas activés ». Cet adaptateur :
 *
 * - valide et normalise le numéro (E.164) ;
 * - journalise l'envoi (sans PII : jamais le corps ni le numéro complet) ;
 * - retourne un identifiant de message déterministe (hash) → le flux
 *   CrmChannelService (consentement, quota, persistance, dead-letter) reste
 *   totalement exercé et testable.
 *
 * Le jour où un fournisseur SMS production est activé, seul cet adaptateur
 * change (implémentation HTTP + webhook signé) — le CRM n'est pas couplé.
 */
final class SmsAdapter implements ChannelAdapterContract
{
    public function __construct(private readonly CrmPhoneNormalizer $normalizer) {}

    public function send(string $toAddress, ?string $body, ?string $templateName, array $settings): array
    {
        $hash = substr(hash('sha256', $toAddress.'|'.($body ?? '')), 0, 24);

        // Audit-only : le corps n'est JAMAIS journalisé (PII). On logue un
        // identifiant de trace déterministe et le canal.
        Log::info('CRM SMS (audit-only) : envoi simulé', [
            'trace_id' => $hash,
            'channel_settings' => array_keys($settings),
        ]);

        return [
            'provider_message_id' => 'sms_audit_'.$hash,
            'status' => 'sent',
            'cost' => 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings  configuration non sensible du canal
     */
    public function verify(string $address, array $settings): bool
    {
        return $this->normalizer->normalizePhone($address) !== null;
    }

    public function normalize(string $address): ?string
    {
        return $this->normalizer->normalizePhone($address);
    }

    /**
     * @param  array<string, mixed>  $settings  configuration non sensible du canal
     */
    public function revoke(string $providerMessageId, array $settings): bool
    {
        // Aucun fournisseur production : rien à révoquer (best-effort ack).
        return true;
    }

    public function channelType(): string
    {
        return CrmChannelType::SMS;
    }
}
