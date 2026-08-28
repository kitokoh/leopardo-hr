<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * #5717 — Étapes d'opportunité CRM client (whitelist).
 *
 * Aligné sur le défaut de la migration `crm_opportunities.stage`
 * (`prospection` — #5709) et sur les étapes exposées par l'UI web (#5715).
 * Toute autre valeur est rejetée en 422.
 */
enum CrmOpportunityStage: string
{
    case Prospecting = 'prospecting';
    case Qualification = 'qualification';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';
}
