<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Infrastructure\Connectors;

use App\Modules\SearchIntelligence\Domain\Contracts\IntegrationMetricsClient;
use App\Modules\SearchIntelligence\Domain\Contracts\IntegrationMetricRow;
use App\Modules\SearchIntelligence\Domain\Enums\MetricSource;
use App\Modules\SearchIntelligence\Domain\Exceptions\IntegrationSyncException;

/**
 * GSI-12 (#8523) point 1 — connecteur réel **Google Search Console**.
 *
 * GSC n'expose pas de « campagnes » : la maille organique est le site. On
 * normalise donc une ligne par JOUR et par SITE (identifiant externe =
 * `site_url`), ce qui permet de stocker les impressions/clics organiques
 * réels dans `campaign_metrics` sans prétendre qu'il s'agit d'une campagne
 * payante. Le coût et les conversions restent à 0 : GSC ne les connaît pas, et
 * les inventer serait exactement l'erreur que l'issue interdit.
 *
 * Endpoint : `searchAnalytics/query` (dimension `date`, agrégation par site).
 */
final class GoogleSearchConsoleConnector extends AbstractGoogleConnector implements IntegrationMetricsClient
{
    public function provider(): string
    {
        return 'gsc';
    }

    public function source(): MetricSource
    {
        return MetricSource::Integration;
    }

    /**
     * @param  array<string, string>  $credentials
     * @return list<IntegrationMetricRow>
     */
    public function fetchDailyMetrics(array $credentials, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $siteUrl = $credentials['site_url'] ?? '';

        if ($siteUrl === '') {
            throw IntegrationSyncException::unauthorized($this->provider());
        }

        $token = $this->accessToken($credentials);

        $payload = $this->getJson(
            'https://searchconsole.googleapis.com/webmasters/v3/sites/'.rawurlencode($siteUrl).'/searchAnalytics/query',
            $token,
            [
                'startDate' => $from->format('Y-m-d'),
                'endDate' => $to->format('Y-m-d'),
                'dimensions' => ['date'],
                'rowLimit' => 366,
            ],
        );

        $rows = $payload['rows'] ?? [];

        // Pas de clé `rows` = réponse inattendue. Clé présente et vide = zéro
        // trafic réel, ce qui est une information légitime à stocker.
        if (! is_array($rows) && ! array_key_exists('rows', $payload)) {
            throw IntegrationSyncException::malformed($this->provider(), 'clé « rows » absente');
        }

        $out = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $date = $row['keys'][0] ?? null;

                if (! is_string($date)) {
                    throw IntegrationSyncException::malformed($this->provider(), 'date absente d\'une ligne');
                }

                $measuredOn = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

                if ($measuredOn === false) {
                    throw IntegrationSyncException::malformed($this->provider(), 'date invalide : '.$date);
                }

                $out[] = new IntegrationMetricRow(
                    campaignExternalId: $siteUrl,
                    measuredOn: $measuredOn->setTime(0, 0),
                    impressions: $this->toInt($row['impressions'] ?? 0),
                    clicks: $this->toInt($row['clicks'] ?? 0),
                );
            }
        }

        return $out;
    }
}
