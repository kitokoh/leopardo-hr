<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Contracts\CampaignConsentCheckerInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vérification du consentement via la table `crm_consents` — Issue #5724.
 *
 * Fail-closed : tant que la table n'existe pas (avant le merge de #5722),
 * aucun contact n'est autorisé — jamais d'envoi sans consentement vérifiable.
 * Dès que la table est présente, seuls les contacts `granted` actifs sur le
 * canal (finalité marketing) passent.
 */
final class ConsentTableCampaignConsentChecker implements CampaignConsentCheckerInterface
{
    public function allows(int $contactId, string $channel): bool
    {
        if (! Schema::hasTable('crm_consents')) {
            return false;
        }

        return DB::table('crm_consents')
            ->where('company_id', currentCompany()?->id)
            ->where('contact_id', $contactId)
            ->where('channel', $channel)
            ->where('purpose', 'marketing')
            ->where('status', 'granted')
            ->exists();
    }
}
