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
 *  1. Si la table tenant `crm_consents` existe (livrée par #5722),
 *     un opt-in explicite (contact_id, channel, purpose) est exigé.
 *  2. Sinon (V0/V1 en cours de merge), fallback configurable
 *     `crm.channels.consent_fallback` : `deny` (défaut, fail-closed) ou
 *     `allow` (environnements de test uniquement).
 *
 * Contrat réel (#9679) : table `crm_consents`, colonnes
 * (company_id, contact_id, channel, purpose, status, granted_at, revoked_at).
 * L'opt-in est porté par `status = 'granted'` ET `revoked_at IS NULL` —
 * il n'existe pas de colonne booléenne `opted_in`.
 */
final class CrmConsentGuard
{
    public function assertConsent(?string $contactId, string $channel, string $purpose): void
    {
        if ($contactId === null || $contactId === '') {
            return;
        }

        // #9741 — `crm_consents.contact_id` est un `unsignedBigInteger`. Un
        // identifiant non numérique (« contact-9 ») n'a donc AUCUN
        // consentement possible : sans ce garde, Postgres levait
        // `22P02 invalid input syntax for type bigint` et l'appelant recevait
        // un 500 au lieu du refus métier 422. On refuse explicitement.
        if (! ctype_digit($contactId)) {
            throw new CrmConsentRequiredException;
        }

        if (! $this->consentsTableExists()) {
            $fallback = (string) config('crm.channels.consent_fallback', 'deny');
            if ($fallback === 'allow') {
                return;
            }

            throw new CrmConsentRequiredException;
        }

        $optIn = DB::table('crm_consents')
            ->where('company_id', currentCompany()->id)
            ->where('contact_id', $contactId)
            ->where('channel', $channel)
            ->where('purpose', $purpose)
            ->where('status', 'granted')
            ->whereNull('revoked_at')
            ->exists();

        if (! $optIn) {
            throw new CrmConsentRequiredException;
        }
    }

    private function consentsTableExists(): bool
    {
        return Schema::hasTable('crm_consents');
    }
}
