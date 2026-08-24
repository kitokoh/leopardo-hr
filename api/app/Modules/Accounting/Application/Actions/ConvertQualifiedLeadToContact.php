<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Actions;

use App\Modules\Accounting\Domain\Enums\ContactSource;
use App\Modules\Accounting\Domain\Enums\ContactType;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Marketing\Domain\Models\MarketingLead;

/**
 * Conversion d'un lead qualifié en contact de facturation — issue #5231.
 *
 * Crée un `AccountingContact` pré-rempli (source=marketing_lead) dans le
 * tenant qui a qualifié le lead, et marque le lead converti
 * (`status=converted`, `converted_company_id`) pour la traçabilité.
 *
 * Le `company_id` est posé explicitement (tenant qui réclame le lead) ;
 * le scope global `BelongsToCompany` reste la garde d'isolation.
 */
class ConvertQualifiedLeadToContact
{
    public function execute(MarketingLead $lead, string $companyId): AccountingContact
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::query()->create([
            'company_id' => $companyId,
            'type' => ContactType::Customer->value,
            'name' => $this->contactName($lead),
            'email' => $lead->email,
            'language' => $lead->locale !== '' ? $lead->locale : null,
            'source' => ContactSource::MarketingLead->value,
            'marketing_lead_id' => $lead->id,
            'metadata' => [
                'lead_external_id' => $lead->external_id,
                'lead_type' => $lead->type,
            ],
        ]);

        $lead->markConverted($companyId);
        $lead->save();

        return $contact;
    }

    /**
     * Nom du contact : payload.name si présent, sinon la partie locale de
     * l'email, sinon un libellé de repli.
     */
    private function contactName(MarketingLead $lead): string
    {
        $payload = $lead->payload;

        if (is_array($payload) && isset($payload['name']) && is_string($payload['name']) && trim($payload['name']) !== '') {
            return trim($payload['name']);
        }

        $localPart = explode('@', $lead->email)[0];

        return $localPart !== '' ? $localPart : 'Lead '.$lead->id;
    }
}
