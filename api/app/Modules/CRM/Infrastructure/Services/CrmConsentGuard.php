<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Exceptions\CrmConsentRequiredException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Garde de consentement de communication CRM (issue #5722 interop).
 *
 * Ordre de résolution :
 *  1. Si la table tenant `crm_contact_consents` existe (livrée par #5722),
 *     un opt-in explicite (contact_id, channel, purpose) est exigé.
 *  2. Sinon (V0/V1 en cours de merge), fallback configurable
 *     `crm.channels.consent_fallback` : `deny` (défaut, fail-closed) ou
 *     `allow` (environnements de test uniquement).
 *
 * Contrat documenté avec #5722 : colonnes attendues
 * (company_id, contact_id, channel, purpose, opted_in, updated_at).
 */
final class CrmConsentGuard
{
    public function assertConsent(?string $contactId, string $channel, string $purpose): void
    {
        if ($contactId === null || $contactId === '') {
            return;
        }

        if (! $this->consentsTableExists()) {
            $fallback = (string) config('crm.channels.consent_fallback', 'deny');
            if ($fallback === 'allow') {
                return;
            }

            throw new CrmConsentRequiredException();
        }

        $optIn = DB::table('crm_contact_consents')
            ->where('company_id', currentCompany()->id)
            ->where('contact_id', $contactId)
            ->where('channel', $channel)
            ->where('purpose', $purpose)
            ->where('opted_in', true)
            ->exists();

        if (! $optIn) {
            throw new CrmConsentRequiredException();
        }
    }

    private function consentsTableExists(): bool
    {
        return Schema::hasTable('crm_contact_consents');
    }
}
