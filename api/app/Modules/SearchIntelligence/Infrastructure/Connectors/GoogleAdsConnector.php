<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Infrastructure\Connectors;

use App\Modules\SearchIntelligence\Domain\Contracts\IntegrationMetricsClient;
use App\Modules\SearchIntelligence\Domain\Contracts\IntegrationMetricRow;
use App\Modules\SearchIntelligence\Domain\Enums\MetricSource;
use App\Modules\SearchIntelligence\Domain\Exceptions\IntegrationSyncException;
use Illuminate\Support\Facades\Http;

/**
 * GSI-12 (#8523) point 1 — connecteur réel **Google Ads**.
 *
 * Google Ads n'a pas d'endpoint REST « métriques par jour » : il faut passer
 * par l'API `googleAds:search` avec une requête GAQL. Trois particularités
 * sont traitées ici et nulle part ailleurs :
 *
 * 1. **Le `developer_token` est un en-tête**, pas un paramètre. Sans lui, la
 *    régie renvoie 403 même avec un jeton OAuth valide — l'erreur est donc
 *    classée « reconnexion requise » pour ne pas boucler indéfiniment.
 * 2. **Le coût est en micros.** `cost_micros` divisé par 10 000 donne des
 *    centimes ; ne pas le diviser produirait des dépenses 10 000 fois trop
 *    élevées dans le tableau de bord.
 * 3. **Chaque campagne est stable dans `campaign.id`** ; on préfère l'ID
 *    numérique au nom, car un nom peut être renommé sans casser le
 *    rapprochement.
 *
 * La requête est paginée par `pageToken` : une fenêtre de 30 jours sur un
 * compte actif dépasse largement une page.
 */
final class GoogleAdsConnector extends AbstractGoogleConnector implements IntegrationMetricsClient
{
    private const API_VERSION = 'v18';

    /** Sécurité anti-boucle : au-delà, on préfère une erreur explicite. */
    private const MAX_PAGES = 40;

    public function provider(): string
    {
        return 'google_ads';
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
        $customerId = preg_replace('/\D/', '', $credentials['customer_id'] ?? '') ?? '';
        $developerToken = $credentials['developer_token'] ?? '';

        if ($customerId === '' || $developerToken === '') {
            throw IntegrationSyncException::unauthorized($this->provider());
        }

        $token = $this->accessToken($credentials);

        // GAQL : une ligne par jour et par campagne. `segments.date` est la
        // seule dimension temporelle admise avec `campaign.id`.
        $query = <<<GAQL
            SELECT campaign.id, campaign.name, segments.date, metrics.cost_micros,
                   metrics.impressions, metrics.clicks, metrics.conversions,
                   metrics.conversions_value
            FROM campaign
            WHERE segments.date BETWEEN '{$from->format('Y-m-d')}' AND '{$to->format('Y-m-d')}'
            GAQL;

        $rows = [];
        $pageToken = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $body = ['query' => $query];

            if ($pageToken !== null) {
                $body['pageToken'] = $pageToken;
            }

            $response = Http::withToken($token)
                ->withHeaders([
                    'developer-token' => $developerToken,
                    'login-customer-id' => $customerId,
                ])
                ->timeout(self::TIMEOUT_SECONDS)
                ->acceptJson()
                ->post(
                    sprintf('https://googleads.googleapis.com/%s/customers/%s/googleAds:search', self::API_VERSION, $customerId),
                    $body,
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

            $batch = $payload['results'] ?? [];

            if (! is_array($batch)) {
                throw IntegrationSyncException::malformed($this->provider(), 'clé « results » invalide');
            }

            foreach ($batch as $entry) {
                $rows[] = $entry;
            }

            $next = $payload['pageToken'] ?? null;

            if (! is_string($next) || $next === '') {
                break;
            }

            $pageToken = $next;
        }

        $out = [];

        foreach ($rows as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $campaign = $entry['campaign'] ?? [];
            $segments = $entry['segments'] ?? [];
            $metrics = $entry['metrics'] ?? [];

            if (! is_array($campaign) || ! is_array($segments) || ! is_array($metrics)) {
                throw IntegrationSyncException::malformed($this->provider(), 'ligne sans campaign/segments/metrics');
            }

            $externalId = $campaign['id'] ?? null;
            $date = $segments['date'] ?? null;

            if (! is_string($externalId) && ! is_int($externalId)) {
                throw IntegrationSyncException::malformed($this->provider(), 'campaign.id absent');
            }

            if (! is_string($date)) {
                throw IntegrationSyncException::malformed($this->provider(), 'segments.date absent');
            }

            $measuredOn = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

            if ($measuredOn === false) {
                throw IntegrationSyncException::malformed($this->provider(), 'date invalide : '.$date);
            }

            $out[] = new IntegrationMetricRow(
                campaignExternalId: (string) $externalId,
                measuredOn: $measuredOn->setTime(0, 0),
                spendCents: $this->microsToCents($metrics['costMicros'] ?? $metrics['cost_micros'] ?? 0),
                impressions: $this->toInt($metrics['impressions'] ?? 0),
                clicks: $this->toInt($metrics['clicks'] ?? 0),
                conversions: $this->toInt($metrics['conversions'] ?? 0),
                revenueCents: $this->toCents($metrics['conversionsValue'] ?? $metrics['conversions_value'] ?? 0),
            );
        }

        return $out;
    }
}
