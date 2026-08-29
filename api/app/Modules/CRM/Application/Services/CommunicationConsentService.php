<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\CRM\Domain\Enums\ConsentChannel;
use App\Modules\CRM\Domain\Enums\ConsentPurpose;
use App\Modules\CRM\Domain\Enums\ConsentSource;
use App\Modules\CRM\Domain\Enums\ConsentStatus;
use App\Modules\CRM\Domain\Events\CrmConsentRevoked;
use App\Modules\CRM\Domain\Models\CrmConsent;
use Illuminate\Support\Facades\Auth;

/**
 * Gestion des consentements CRM — Issue #5722.
 *
 * Règles :
 *   - un seul état courant par (contact, canal, finalité) — la table
 *     `crm_consents` porte l'état, l'historique immuable vit dans
 *     `audit_logs` (RGPD art. 7 : preuve du consentement) ;
 *   - `grant` / `deny` / `withdraw` tracent chaque mutation ;
 *   - `withdraw` dispatche `CrmConsentRevoked` : les envois de campagne en
 *     attente du contact sont annulés (aucun envoi sans consentement requis) ;
 *   - `allows()` est la garde unique consommée par les canaux d'envoi
 *     (campagnes #5724, email #5726) : pas de consentement granted = pas
 *     d'envoi marketing.
 */
final class CommunicationConsentService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function grant(
        int $contactId,
        ConsentChannel $channel,
        ConsentPurpose $purpose,
        ConsentSource $source,
        ?string $sourceRef = null,
        array $metadata = [],
    ): CrmConsent {
        $consent = $this->upsert(
            $contactId,
            $channel,
            $purpose,
            ConsentStatus::Granted,
            $source,
            $sourceRef,
            [
                'granted_at' => now(),
                'revoked_at' => null,
                'metadata' => $metadata === [] ? null : $metadata,
            ],
        );

        $this->audit($consent, 'consent.granted', ['status' => ConsentStatus::Granted->value]);

        return $consent;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function deny(
        int $contactId,
        ConsentChannel $channel,
        ConsentPurpose $purpose,
        ConsentSource $source,
        ?string $sourceRef = null,
        array $metadata = [],
    ): CrmConsent {
        $consent = $this->upsert(
            $contactId,
            $channel,
            $purpose,
            ConsentStatus::Denied,
            $source,
            $sourceRef,
            [
                'granted_at' => null,
                'revoked_at' => null,
                'metadata' => $metadata === [] ? null : $metadata,
            ],
        );

        $this->audit($consent, 'consent.denied', ['status' => ConsentStatus::Denied->value]);

        return $consent;
    }

    public function withdraw(
        int $contactId,
        ConsentChannel $channel,
        ConsentPurpose $purpose,
        ConsentSource $source,
        ?string $sourceRef = null,
    ): CrmConsent {
        $previous = CrmConsent::query()
            ->where('contact_id', $contactId)
            ->where('channel', $channel->value)
            ->where('purpose', $purpose->value)
            ->first();

        $consent = $this->upsert(
            $contactId,
            $channel,
            $purpose,
            ConsentStatus::Withdrawn,
            $source,
            $sourceRef,
            ['revoked_at' => now()],
        );

        $this->audit($consent, 'consent.withdrawn', [
            'status' => ConsentStatus::Withdrawn->value,
            'previous_status' => $previous?->status,
        ]);

        CrmConsentRevoked::dispatch(
            $consent->company_id,
            $consent->contact_id,
            $consent->channel,
            $consent->purpose,
        );

        return $consent;
    }

    /**
     * Garde d'envoi : un consentement `granted` est-il actif pour ce
     * (contact, canal, finalité) ? Absence de ligne = refus (fail-closed).
     */
    public function allows(
        int $contactId,
        ConsentChannel $channel,
        ConsentPurpose $purpose,
    ): bool {
        $consent = CrmConsent::query()
            ->where('contact_id', $contactId)
            ->where('channel', $channel->value)
            ->where('purpose', $purpose->value)
            ->first();

        return $consent !== null && $consent->status === ConsentStatus::Granted->value;
    }

    /** Consentement marketing actif sur le canal donné. */
    public function allowsMarketing(int $contactId, ConsentChannel $channel): bool
    {
        return $this->allows($contactId, $channel, ConsentPurpose::Marketing);
    }

    /** Au moins un consentement marketing actif (tous canaux confondus). */
    public function hasAnyMarketingConsent(int $contactId): bool
    {
        return CrmConsent::query()
            ->where('contact_id', $contactId)
            ->where('purpose', ConsentPurpose::Marketing->value)
            ->where('status', ConsentStatus::Granted->value)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $extraAttributes
     */
    private function upsert(
        int $contactId,
        ConsentChannel $channel,
        ConsentPurpose $purpose,
        ConsentStatus $status,
        ConsentSource $source,
        ?string $sourceRef,
        array $extraAttributes,
    ): CrmConsent {
        /** @var CrmConsent $consent */
        $consent = CrmConsent::query()->updateOrCreate(
            [
                'contact_id' => $contactId,
                'channel' => $channel->value,
                'purpose' => $purpose->value,
            ],
            array_merge([
                'status' => $status->value,
                'source' => $source->value,
                'source_ref' => $sourceRef,
            ], $extraAttributes),
        );

        return $consent;
    }

    /**
     * Trace immuable de la mutation (RGPD art. 7 — preuve du consentement).
     *
     * @param  array<string, mixed>  $newValues
     */
    private function audit(CrmConsent $consent, string $action, array $newValues): void
    {
        $userId = Auth::guard('sanctum')->id();

        AuditLog::create([
            'company_id' => $consent->company_id,
            'user_id' => $userId !== null ? (int) $userId : null,
            'action' => $action,
            'module' => 'crm',
            'auditable_type' => CrmConsent::class,
            'auditable_id' => $consent->id,
            'new_values' => $newValues,
            'metadata' => [
                'contact_id' => $consent->contact_id,
                'channel' => $consent->channel,
                'purpose' => $consent->purpose,
                'source' => $consent->source,
                'source_ref' => $consent->source_ref,
            ],
        ]);
    }
}
