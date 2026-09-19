<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Models\CrmContact;
use App\Shared\Contracts\Crm\EmailFollowUpConsentGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Implementation CRM du contrat partage `EmailFollowUpConsentGate`
 * (BC-11, pour le moteur de relances Communication R4 #7689).
 *
 * Signaux verifies (fail-closed sur les signaux negatifs) :
 * 1. `crm_email_suppressions` (#5726) : bounce / plainte / unsubscribe —
 *    adresses hachees sha256, normalisees en minuscules ;
 * 2. `crm_consents` (#5722) : contact correspondant (email insensible a la
 *    casse) avec un consentement canal `email` RETIRE (`withdrawn`) ou
 *    REFUSE (`denied`), toutes finalites confondues.
 *
 * Les tables sont livrees par les lots CRM : leur absence (schema partiel)
 * ne bloque pas — seul le signal disponible est evalue (les garde-fous
 * locaux du module Communication — opt-out, reponse detectee, plafonds —
 * restent toujours actifs).
 */
class CrmEmailFollowUpConsentGate implements EmailFollowUpConsentGate
{
    public function allowsFollowUp(string $companyId, string $email): bool
    {
        $normalized = mb_strtolower(trim($email));

        if ($normalized === '') {
            return false;
        }

        if (Schema::hasTable('crm_email_suppressions')) {
            $suppressed = DB::table('crm_email_suppressions')
                ->where('company_id', $companyId)
                ->where('email_hash', hash('sha256', $normalized))
                ->exists();

            if ($suppressed) {
                return false;
            }
        }

        if (! Schema::hasTable('crm_consents')) {
            return true;
        }

        /** @var CrmContact|null $contact */
        $contact = CrmContact::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->orderBy('id')
            ->first();

        if ($contact === null) {
            return true;
        }

        return ! DB::table('crm_consents')
            ->where('company_id', $companyId)
            ->where('contact_id', $contact->id)
            ->where('channel', 'email')
            ->whereIn('status', ['withdrawn', 'denied'])
            ->exists();
    }
}
