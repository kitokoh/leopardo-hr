<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Annulation des envois de campagne à la suite d'un retrait de consentement —
 * Issue #5722 (consommé par le listener PropagateConsentRevocation).
 *
 * La table `crm_campaign_sends` est livrée par l'issue #5724 : tant qu'elle
 * n'existe pas, le handler est un no-op documenté (garde schemaTableExists)
 * — il devient effectif automatiquement au merge de #5724, sans changement
 * de code ici.
 */
final class CampaignConsentRevocationHandler
{
    public function cancelPendingSends(
        string $companyId,
        int $contactId,
        string $channel,
        string $purpose,
    ): int {
        if (! Schema::hasTable('crm_campaign_sends')) {
            return 0;
        }

        if ($purpose !== 'marketing') {
            // Seuls les envois marketing sont soumis au consentement.
            return 0;
        }

        return DB::table('crm_campaign_sends')
            ->where('company_id', $companyId)
            ->where('contact_id', $contactId)
            ->where('channel', $channel)
            ->whereIn('status', ['pending', 'queued'])
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);
    }
}
