<?php

declare(strict_types=1);

namespace App\Support\Gdpr;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * CrmConsentGate — retrait de consentement bloquant les nouveaux envois
 * (issue #5739).
 *
 * Règle RGPD : le retrait d'un consentement bloque les NOUVEAUX envois
 * concernés (canal + finalité), sans effacer l'historique des envois déjà
 * réalisés (l'historique reste audité et soumis à la durée de rétention).
 *
 * Contrat de données (table tenant `crm_consents`, socle V0 #5722) :
 *   company_id, contact_id, channel, purpose, status ('granted'|'revoked'),
 *   timestamps.
 *
 * Comportement fail-closed :
 *  - table absente (CRM V0 non mergé) → refus d'envoi (false) + log structuré
 *    SANS PII (aucun canal/finalité ni identifiant de contact) ;
 *  - aucun consentement enregistré → refus (pas de consentement = pas d'envoi) ;
 *  - consentement révoqué → refus ;
 *  - consentement accordé et non révoqué → autorisé.
 */
final class CrmConsentGate
{
    public const STATUS_GRANTED = 'granted';

    public const STATUS_REVOKED = 'revoked';

    /**
     * Un envoi sur le canal/finalité donné est-il autorisé pour ce contact ?
     */
    public function canSend(string $channel, string $purpose, string $contactId, string $companyId): bool
    {
        if (! Schema::hasTable('crm_consents')) {
            Log::channel('structured')->warning('crm.consent.table_missing_fail_closed');

            return false;
        }

        $columns = Schema::getColumnListing('crm_consents');
        if (! in_array('status', $columns, true)) {
            Log::channel('structured')->warning('crm.consent.schema_incomplete_fail_closed');

            return false;
        }

        $row = DB::table('crm_consents')
            ->where('company_id', $companyId)
            ->where('contact_id', $contactId)
            ->where('channel', $channel)
            ->where('purpose', $purpose)
            ->orderByDesc('created_at')
            ->first();

        if ($row === null) {
            return false;
        }

        $status = strval($row->status ?? '');

        return $status === self::STATUS_GRANTED;
    }
}
