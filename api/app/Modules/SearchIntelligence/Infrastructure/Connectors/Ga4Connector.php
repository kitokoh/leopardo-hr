<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Infrastructure\Connectors;

use App\Modules\SearchIntelligence\Domain\Contracts\IntegrationMetricsClient;
use App\Modules\SearchIntelligence\Domain\Contracts\IntegrationMetricRow;
use App\Modules\SearchIntelligence\Domain\Enums\MetricSource;
use App\Modules\SearchIntelligence\Domain\Exceptions\IntegrationSyncException;

/**
 * GSI-12 (#8523) point 1 — connecteur réel **GA4 Data API**.
 *
 * La maille GA4 retenue est `date × sessionCampaignName` : c'est la seule
 * dimension qui corresponde à une campagne nommée côté site, et donc la seule
 * qui puisse être rapprochée d'une ligne `campaigns` interne. Les sessions
 * organiques (campagne vide ou `(organic)`) sont normalisées sous l'identifiant
 * `ga4:organic` plutôt que perdues : elles existent, les jeter fausserait le
 * total.
 *
 * GA4 ne remonte ni coût ni revenu média : `spend_cents` reste 0. Le revenu
 * éventuel (`totalRevenue`) est stocké, lui, car c'est une mesure réelle de la
 * plateforme.
 *
 * Endpoint : `runReport` (v1beta, POST JSON).
 */
final class Ga4Connector extends AbstractGoogleConnector implements IntegrationMetricsClient
{
    public function provider(): string
    {
        return 'ga4';
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
        $propertyId = $credentials['property_id'] ?? '';

        if ($propertyId === '') {
            throw IntegrationSyncException::unauthorized($this->provider());
        }

        $token = $this->accessToken($credentials);

        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->timeout(self::TIMEOUT_SECONDS)
            ->acceptJson()
            ->post(
                'https://analyticsdata.googleapis.com/v1beta/properties/'.rawurlencode($propertyId).':runReport',
                [
                    'dateRanges' => [[
                        'startDate' => $from->format('Y-m-d'),
                        'endDate' => $to->format('Y-m-d'),
                    ]],
                    'dimensions' => [
                        ['name' => 'date'],
                        ['name' => 'sessionCampaignName'],
                    ],
                    'metrics' => [
                        ['name' => 'sessions'],
                        ['name' => 'conversions'],
                        ['name' => 'totalRevenue'],
                    ],
                    'limit' => 10000,
                ],
            );

        if ($response->status() === 401 || $response->status() === 403) {
            throw IntegrationSyncException::unauthorized($this->provider());
        }

        if ($response->status() === 429) {
            throw IntegrationSyncException::quotaExceeded($this->provider());
        }

        if ($response->serverError()) {
            throw IntegrationSyncException::transport($this->provider(), 'HTTP '.$response->status());
        }

        if (! $response->successful()) {
            throw IntegrationSyncException::malformed($this->provider(), 'HTTP '.$response->status());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw IntegrationSyncException::malformed($this->provider(), 'réponse non JSON');
        }

        $rows = $payload['rows'] ?? [];

        if (! is_array($rows) || ! array_key_exists('rows', $payload)) {
            throw IntegrationSyncException::malformed($this->provider(), 'clé « rows » absente ou invalide');
        }

        $out = [];
        $index = $this->metricIndex($payload);

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $dims = $row['dimensionValues'] ?? [];
            $mets = $row['metricValues'] ?? [];

            if (! is_array($dims) || ! is_array($mets)) {
                throw IntegrationSyncException::malformed($this->provider(), 'ligne sans dimensions/métriques');
            }

            $rawDate = $dims[0]['value'] ?? null;
            $campaign = $dims[1]['value'] ?? null;

            if (! is_string($rawDate)) {
                throw IntegrationSyncException::malformed($this->provider(), 'date absente d\'une ligne');
            }

            // GA4 renvoie AAAAMMJJ.
            if (strlen($rawDate) !== 8 || ! ctype_digit($rawDate)) {
                throw IntegrationSyncException::malformed($this->provider(), 'date GA4 invalide : '.$rawDate);
            }

            $measuredOn = \DateTimeImmutable::createFromFormat(
                'Ymd',
                $rawDate,
            );

            if ($measuredOn === false) {
                throw IntegrationSyncException::malformed($this->provider(), 'date GA4 invalide : '.$rawDate);
            }

            $campaignKey = (is_string($campaign) && $campaign !== '')
                ? $campaign
                : 'ga4:organic';

            $out[] = new IntegrationMetricRow(
                campaignExternalId: $campaignKey,
                measuredOn: $measuredOn->setTime(0, 0),
                conversions: $this->metricAt($mets, $index, 'conversions'),
                revenueCents: $this->toCents($this->metricRaw($mets, $index, 'totalRevenue')),
            );
        }

        return $out;
    }

    /**
     * GA4 nomme ses métriques dans l'ordre demandé ; on résout par nom quand
     * la réponse les nomme, sinon on retombe sur l'ordre de la requête.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, int>
     */
    private function metricIndex(array $payload): array
    {
        $headers = $payload['metricHeaders'] ?? [];

        if (! is_array($headers)) {
            return [];
        }

        $index = [];

        foreach (array_values($headers) as $position => $header) {
            if (is_array($header) && isset($header['name']) && is_string($header['name'])) {
                $index[$header['name']] = $position;
            }
        }

        return $index;
    }

    /**
     * @param  array<mixed, mixed>  $metricValues
     * @param  array<string, int>  $index
     */
    private function metricRaw(array $metricValues, array $index, string $name): mixed
    {
        $position = $index[$name] ?? match ($name) {
            'sessions' => 0,
            'conversions' => 1,
            'totalRevenue' => 2,
            default => null,
        };

        if ($position === null) {
            return null;
        }

        $cell = $metricValues[$position] ?? null;

        return is_array($cell) ? ($cell['value'] ?? null) : null;
    }

    /**
     * @param  array<mixed, mixed>  $metricValues
     * @param  array<string, int>  $index
     */
    private function metricAt(array $metricValues, array $index, string $name): int
    {
        return $this->toInt($this->metricRaw($metricValues, $index, $name));
    }
}
