<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Contracts;

use App\Modules\SearchIntelligence\Domain\Enums\MetricSource;

/**
 * GSI-12 (#8523) point 1 — contrat d'un connecteur de métriques réel.
 *
 * Un connecteur traduit des identifiants déjà stockés (chiffrés) en relevés
 * normalisés. Il ne persiste RIEN : la persistance, l'idempotence et la
 * reprise sont la responsabilité du job de synchronisation, ce qui garantit
 * qu'un seul endroit écrit `campaign_metrics`.
 *
 * Deux règles non négociables :
 *
 * 1. **Aucune donnée inventée.** Si le fournisseur ne répond pas, si le jeton
 *    est expiré ou si le périmètre est vide, le connecteur lève
 *    `IntegrationSyncException` — il ne renvoie pas une liste vide qui se
 *    lirait comme « aucune performance ».
 *
 * 2. **Le secret ne sort pas.** Les identifiants arrivent en paramètre et ne
 *    sont jamais écrits dans une exception ni dans un log.
 */
interface IntegrationMetricsClient
{
    /** Fournisseur servi par ce client (`gsc`, `ga4`, `google_ads`, `ms_ads`). */
    public function provider(): string;

    /** Provenance portée par les relevés produits. */
    public function source(): MetricSource;

    /**
     * Relevés normalisés sur une fenêtre de dates (bornes incluses).
     *
     * @param  array<string, string>  $credentials  identifiants déchiffrés pour l'appel
     * @return list<IntegrationMetricRow>
     *
     * @throws \App\Modules\SearchIntelligence\Domain\Exceptions\IntegrationSyncException
     */
    public function fetchDailyMetrics(array $credentials, \DateTimeImmutable $from, \DateTimeImmutable $to): array;
}
