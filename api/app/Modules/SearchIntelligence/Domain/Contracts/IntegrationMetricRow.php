<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Contracts;

/**
 * GSI-12 (#8523) point 1 — un relevé quotidien normalisé, tel qu'il sort d'un
 * connecteur et tel qu'il entrera dans `campaign_metrics`.
 *
 * `campaignExternalId` est l'identifiant chez le fournisseur (nom de campagne
 * Google Ads, etc.). Le job de synchronisation le résout en `campaigns.id`
 * interne ; un identifiant inconnu est IGNORÉ et compté, jamais créé
 * implicitement — sinon une campagne mal saisie chez la régie ferait
 * apparaître une campagne fantôme chez nous.
 */
final readonly class IntegrationMetricRow
{
    public function __construct(
        public string $campaignExternalId,
        public \DateTimeImmutable $measuredOn,
        public int $spendCents = 0,
        public int $impressions = 0,
        public int $clicks = 0,
        public int $conversions = 0,
        public int $revenueCents = 0,
    ) {
    }

    /** Le relevé ne porte aucune mesure : il n'a pas à être stocké. */
    public function isEmpty(): bool
    {
        return $this->spendCents === 0
            && $this->impressions === 0
            && $this->clicks === 0
            && $this->conversions === 0
            && $this->revenueCents === 0;
    }
}
