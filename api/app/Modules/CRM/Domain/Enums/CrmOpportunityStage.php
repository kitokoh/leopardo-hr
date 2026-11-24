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

    /**
     * Separateur canonique d'une transition d'etape — #9886.
     *
     * Il etait ecrit en dur a DEUX endroits (`notes` de l'opportunite et
     * `description` de la `CrmActivity`) avec des valeurs differentes : `->`
     * d'un cote, `→` de l'autre. Deux separateurs pour la meme information
     * rendent l'historique non interrogeable (`LIKE '%→%'` ne retrouve que la
     * moitie des lignes) et cassent toute analyse du pipeline.
     *
     * Une seule constante, consommee par les deux ecrivains, pour qu'ils ne
     * puissent plus diverger.
     */
    public const TRANSITION_SEPARATOR = '→';

    /**
     * Formate une transition pour le journal (`notes`) et la timeline.
     *
     * Exemple : `prospecting → proposal`.
     */
    public static function formatTransition(string $from, string $to): string
    {
        return $from.' '.self::TRANSITION_SEPARATOR.' '.$to;
    }
}
