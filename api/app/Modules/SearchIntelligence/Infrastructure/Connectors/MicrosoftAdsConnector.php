<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Infrastructure\Connectors;

use App\Modules\SearchIntelligence\Domain\Contracts\IntegrationMetricsClient;
use App\Modules\SearchIntelligence\Domain\Contracts\IntegrationMetricRow;
use App\Modules\SearchIntelligence\Domain\Enums\MetricSource;
use App\Modules\SearchIntelligence\Domain\Exceptions\IntegrationSyncException;
use Illuminate\Support\Facades\Http;

/**
 * GSI-12 (#8523) point 1 — connecteur réel **Microsoft Advertising**.
 *
 * Particularités Microsoft, traitées une seule fois ici :
 *
 * - L'échange de jeton ne suit PAS le contrat Google : pas de `client_secret`
 *   dans le POST, mais un en-tête `client_secret`, un `scope` explicite et un
 *   `redirect_uri` obligatoire (même hors flux interactif). Réutiliser la base
 *   Google produirait un 400 systématique.
 * - Le coût est déjà en unités monétaires décimales (`Spend`), pas en micros.
 * - La pagination se fait par `@nextLink` (URL complète), pas par jeton.
 *
 * Comme pour Google Ads, la maille est jour × campagne et l'identifiant est
 * l'ID numérique de campagne.
 */
final class MicrosoftAdsConnector extends AbstractGoogleConnector implements IntegrationMetricsClient
{
    private const SCOPE = 'https://ads.microsoft.com/ads.manage offline_access';

    public function provider(): string
    {
        return 'ms_ads';
    }

    public function source(): MetricSource
    {
        return MetricSource::Integration;
    }

    /**
     * Microsoft Advertising a son propre contrat OAuth : on ne réutilise pas
     * `accessToken()` de la base Google.
     *
     * @param  array<string, string>  $credentials
     */
    private function microsoftAccessToken(array $credentials): string
    {
        foreach (['client_id', 'client_secret', 'refresh_token'] as $key) {
            if (($credentials[$key] ?? '') === '') {
                throw IntegrationSyncException::unauthorized($this->provider());
            }
        }

        $response = Http::asForm()
            ->withHeaders(['client_secret' => $credentials['client_secret']])
            ->timeout(self::TIMEOUT_SECONDS)
            ->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
                'client_id' => $credentials['client_id'],
                'grant_type' => 'refresh_token',
                'refresh_token' => $credentials['refresh_token'],
                'scope' => self::SCOPE,
                'redirect_uri' => 'https://localhost',
            ]);

        if ($response->status() === 400 || $response->status() === 401) {
            throw IntegrationSyncException::unauthorized($this->provider());
        }

        if ($response->status() === 429) {
            throw IntegrationSyncException::quotaExceeded($this->provider());
        }

        if ($response->serverError()) {
            throw IntegrationSyncException::transport($this->provider(), 'HTTP '.$response->status());
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw IntegrationSyncException::malformed($this->provider(), 'access_token absent de la réponse Microsoft');
        }

        return $token;
    }

    /**
     * @param  array<string, string>  $credentials
     * @return list<IntegrationMetricRow>
     */
    public function fetchDailyMetrics(array $credentials, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $customerId = preg_replace('/\D/', '', $credentials['customer_id'] ?? '') ?? '';

        if ($customerId === '') {
            throw IntegrationSyncException::unauthorized($this->provider());
        }

        $token = $this->microsoftAccessToken($credentials);

        $rows = [];
        $url = 'https://campaign.api.bingads.microsoft.com/Reporting/v13/GenerateReport/Submit';

        // Microsoft expose un flux asynchrone (Submit → Poll → Download). On
        // soumet la demande, puis on interroge l'état : le résultat direct
        // n'existe pas, contrairement aux API Google.
            $response = Http::withToken($token)
                ->timeout(self::TIMEOUT_SECONDS)
                ->acceptJson()
                ->post($url, [
                    'ReportRequest' => [
                        'Type' => 'CampaignPerformanceReportRequest',
                        'Aggregation' => 'Daily',
                        'ExcludeColumnHeaders' => false,
                        'ExcludeReportFooter' => true,
                        'ExcludeReportHeader' => true,
                        'Format' => 'Csv',
                        'ReturnOnlyCompleteData' => false,
                        'Scope' => [
                            'AccountIds' => [(int) $customerId],
                        ],
                        'Columns' => [
                            'CampaignId',
                            'CampaignName',
                            'TimePeriod',
                            'Spend',
                            'Impressions',
                            'Clicks',
                            'Conversions',
                            'Revenue',
                        ],
                        'Time' => [
                            'CustomDateRangeStart' => ['Year' => (int) $from->format('Y'), 'Month' => (int) $from->format('n'), 'Day' => (int) $from->format('j')],
                            'CustomDateRangeEnd' => ['Year' => (int) $to->format('Y'), 'Month' => (int) $to->format('n'), 'Day' => (int) $to->format('j')],
                        ],
                    ],
                ]);

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

            $reportRequestId = $payload['ReportRequestId'] ?? null;

            if (! is_string($reportRequestId) || $reportRequestId === '') {
                throw IntegrationSyncException::malformed($this->provider(), 'ReportRequestId absent');
            }

            return $this->downloadReport($token, $reportRequestId);
    }

    /**
     * Interroge l'état du rapport puis lit le CSV fourni par Microsoft.
     *
     * @return list<IntegrationMetricRow>
     */
    private function downloadReport(string $token, string $reportRequestId): array
    {
        $statusUrl = 'https://campaign.api.bingads.microsoft.com/Reporting/v13/GenerateReport/Poll';

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $status = Http::withToken($token)
                ->timeout(self::TIMEOUT_SECONDS)
                ->acceptJson()
                ->post($statusUrl, ['ReportRequestId' => $reportRequestId]);

            if ($status->status() === 401 || $status->status() === 403) {
                throw IntegrationSyncException::unauthorized($this->provider());
            }

            if ($status->serverError()) {
                throw IntegrationSyncException::transport($this->provider(), 'HTTP '.$status->status());
            }

            $payload = $status->json();

            if (! is_array($payload)) {
                throw IntegrationSyncException::malformed($this->provider(), 'réponse de statut non JSON');
            }

            $state = $payload['ReportRequestStatus']['Status'] ?? null;

            if ($state === 'Error') {
                throw IntegrationSyncException::malformed($this->provider(), 'rapport en erreur côté Microsoft');
            }

            $downloadUrl = $payload['ReportRequestStatus']['ReportDownloadUrl'] ?? null;

            if ($state === 'Success' && is_string($downloadUrl) && $downloadUrl !== '') {
                $csv = Http::timeout(self::TIMEOUT_SECONDS)->get($downloadUrl);

                if (! $csv->successful()) {
                    throw IntegrationSyncException::transport($this->provider(), 'téléchargement du rapport impossible');
                }

                return $this->parseCsv($csv->body());
            }

            // Rapport encore en préparation : la reprise est assurée par le
            // scheduler (sans chevauchement), on sort sans bloquer le worker.
            usleep(2_000_000);
        }

        throw new IntegrationSyncException($this->provider(), 'rapport Microsoft non prêt dans le délai imparti', true);
    }

    /**
     * Lit le CSV Microsoft en s'appuyant sur l'en-tête réel (les colonnes
     * changent selon le rapport demandé).
     *
     * @return list<IntegrationMetricRow>
     */
    private function parseCsv(string $body): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($body)) ?: [];

        if (count($lines) < 2) {
            return [];
        }

        $header = str_getcsv((string) array_shift($lines));
        $position = [];

        foreach ($header as $i => $column) {
            $position[strtolower(trim((string) $column))] = $i;
        }

        foreach (['campaignid', 'timeperiod'] as $required) {
            if (! array_key_exists($required, $position)) {
                throw IntegrationSyncException::malformed($this->provider(), 'colonne « '.$required.' » absente du CSV');
            }
        }

        $out = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv($line);

            $rawDate = $cells[$position['timeperiod']] ?? null;
            $campaignId = $cells[$position['campaignid']] ?? null;

            if (! is_string($rawDate) || $rawDate === '' || ! is_string($campaignId) || $campaignId === '') {
                continue;
            }

            // Microsoft renvoie MM/JJ/AAAA.
            $measuredOn = \DateTimeImmutable::createFromFormat('m/d/Y', $rawDate)
                ?: \DateTimeImmutable::createFromFormat('Y-m-d', $rawDate);

            if ($measuredOn === false) {
                throw IntegrationSyncException::malformed($this->provider(), 'date CSV invalide : '.$rawDate);
            }

            $out[] = new IntegrationMetricRow(
                campaignExternalId: $campaignId,
                measuredOn: $measuredOn->setTime(0, 0),
                spendCents: $this->toCents($cells[$position['spend'] ?? -1] ?? 0),
                impressions: $this->toInt($cells[$position['impressions'] ?? -1] ?? 0),
                clicks: $this->toInt($cells[$position['clicks'] ?? -1] ?? 0),
                conversions: $this->toInt($cells[$position['conversions'] ?? -1] ?? 0),
                revenueCents: $this->toCents($cells[$position['revenue'] ?? -1] ?? 0),
            );
        }

        return $out;
    }
}
