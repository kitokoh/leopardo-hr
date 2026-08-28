<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * #5717 — Statuts de prospect (lead) CRM client.
 *
 * Whitelist stricte (ADR-CRM-005) : un statut inconnu est rejeté en 422,
 * jamais accepté silencieusement. `converted` est terminal — la conversion
 * (#5717) est la seule transition vers ce statut.
 */
enum CrmLeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Converted = 'converted';
    case Lost = 'lost';
}
