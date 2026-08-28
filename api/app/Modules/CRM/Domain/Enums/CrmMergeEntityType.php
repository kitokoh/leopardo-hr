<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * #5718 — Entités fusionnables (déduplication supervisée).
 *
 * Whitelist stricte (ADR-CRM-005) : seules accounts/contacts/leads sont
 * éligibles à la détection de doublons et à la fusion.
 */
enum CrmMergeEntityType: string
{
    case Accounts = 'accounts';
    case Contacts = 'contacts';
    case Leads = 'leads';
}
