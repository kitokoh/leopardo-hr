<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Type de la cible d'une activité ou tâche CRM (timeline polymorphe) —
 * Issue #5710 (CRM-V0-06).
 *
 * `contact`/`account` ciblent les agrégats livrés par #5708 ; les FK restent
 * logiques (company_id sur chaque extrémité, aucun croisement cross-tenant).
 */
enum CrmRelatedType: string
{
    case Lead = 'lead';
    case Opportunity = 'opportunity';
    case Contact = 'contact';
    case Account = 'account';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
