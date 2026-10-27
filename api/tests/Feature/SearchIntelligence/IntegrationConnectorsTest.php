<?php

declare(strict_types=1);

namespace Tests\Feature\SearchIntelligence;

use App\Modules\SearchIntelligence\Domain\Contracts\IntegrationMetricRow;
use App\Modules\SearchIntelligence\Domain\Enums\MetricSource;
use App\Modules\SearchIntelligence\Domain\Exceptions\IntegrationSyncException;
use App\Modules\SearchIntelligence\Infrastructure\Connectors\Ga4Connector;
use App\Modules\SearchIntelligence\Infrastructure\Connectors\GoogleSearchConsoleConnector;
use App\Modules\SearchIntelligence\Infrastructure\Connectors\MicrosoftAdsConnector;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * GSI-12 (#8523) point 1 — les connecteurs réels ne mentent jamais.
 *
 * Ces tests vérifient le contrat qui compte : une panne est levée comme une
 * exception typée (jamais convertie en liste vide), et une réponse valide est
 * normalisée en lignes exploitables.
 *
 * Ils utilisent `Http::fake()` : aucun appel réseau réel, aucun secret réel.
 */
final class IntegrationConnectorsTest extends TestCase
{
    public function test_gsc_normalises_rows_into_daily_metrics(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.fake'], 200),
            'searchconsole.googleapis.com/*' => Http::response([
                'rows' => [
                    ['keys' => ['2026-10-20'], 'clicks' => 12.0, 'impressions' => 340.0],
                    ['keys' => ['2026-10-21'], 'clicks' => 7.0, 'impressions' => 210.0],
                ],
            ], 200),
        ]);

        $rows = (new GoogleSearchConsoleConnector)->fetchDailyMetrics(
            ['client_id' => 'a', 'client_secret' => 'b', 'refresh_token' => 'c', 'site_url' => 'https://leopardo.africa/'],
            new \DateTimeImmutable('2026-10-20'),
            new \DateTimeImmutable('2026-10-21'),
        );

        self::assertCount(2, $rows);
        self::assertSame('https://leopardo.africa/', $rows[0]->campaignExternalId);
        self::assertSame('2026-10-20', $rows[0]->measuredOn->format('Y-m-d'));
        self::assertSame(12, $rows[0]->clicks);
        self::assertSame(340, $rows[0]->impressions);
        // GSC ne connaît pas le coût : il reste à zéro, il n'est pas inventé.
        self::assertSame(0, $rows[0]->spendCents);
    }

    public function test_gsc_without_credentials_is_unauthorized_not_silent(): void
    {
        $this->expectException(IntegrationSyncException::class);

        (new GoogleSearchConsoleConnector)->fetchDailyMetrics(
            ['site_url' => 'https://leopardo.africa/'],
            new \DateTimeImmutable('2026-10-20'),
            new \DateTimeImmutable('2026-10-21'),
        );
    }

    public function test_ga4_normalises_organic_sessions_under_stable_key(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.fake'], 200),
            'analyticsdata.googleapis.com/*' => Http::response([
                'metricHeaders' => [
                    ['name' => 'sessions'],
                    ['name' => 'conversions'],
                    ['name' => 'totalRevenue'],
                ],
                'rows' => [
                    [
                        'dimensionValues' => [['value' => '20261020'], ['value' => '']],
                        'metricValues' => [['value' => '42'], ['value' => '3'], ['value' => '12.50']],
                    ],
                    [
                        'dimensionValues' => [['value' => '20261020'], ['value' => 'promo-octobre']],
                        'metricValues' => [['value' => '10'], ['value' => '1'], ['value' => '0']],
                    ],
                ],
            ], 200),
        ]);

        $rows = (new Ga4Connector)->fetchDailyMetrics(
            ['client_id' => 'a', 'client_secret' => 'b', 'refresh_token' => 'c', 'property_id' => '123'],
            new \DateTimeImmutable('2026-10-20'),
            new \DateTimeImmutable('2026-10-20'),
        );

        self::assertCount(2, $rows);
        self::assertSame('ga4:organic', $rows[0]->campaignExternalId);
        self::assertSame(1250, $rows[0]->revenueCents);
        self::assertSame('promo-octobre', $rows[1]->campaignExternalId);
        self::assertSame(MetricSource::Integration, (new Ga4Connector)->source());
    }

    public function test_ga4_malformed_payload_raises_instead_of_returning_empty(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.fake'], 200),
            'analyticsdata.googleapis.com/*' => Http::response(['unexpected' => true], 200),
        ]);

        $this->expectException(IntegrationSyncException::class);

        (new Ga4Connector)->fetchDailyMetrics(
            ['client_id' => 'a', 'client_secret' => 'b', 'refresh_token' => 'c', 'property_id' => '123'],
            new \DateTimeImmutable('2026-10-20'),
            new \DateTimeImmutable('2026-10-20'),
        );
    }

    public function test_expired_token_is_not_retryable(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        try {
            (new Ga4Connector)->fetchDailyMetrics(
                ['client_id' => 'a', 'client_secret' => 'b', 'refresh_token' => 'c', 'property_id' => '123'],
                new \DateTimeImmutable('2026-10-20'),
                new \DateTimeImmutable('2026-10-20'),
            );
            self::fail('Una exception était attendue pour un jeton refusé.');
        } catch (IntegrationSyncException $e) {
            self::assertFalse($e->retryable, 'Un jeton refusé exige une reconnexion, pas un nouvel essai.');
        }
    }

    public function test_row_emptiness_prevents_useless_writes(): void
    {
        $empty = new IntegrationMetricRow('campaign-1', new \DateTimeImmutable('2026-10-20'));
        $filled = new IntegrationMetricRow('campaign-1', new \DateTimeImmutable('2026-10-20'), clicks: 1);

        self::assertTrue($empty->isEmpty());
        self::assertFalse($filled->isEmpty());
    }

    public function test_microsoft_uses_its_own_oauth_contract(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'ms.fake'], 200),
            'campaign.api.bingads.microsoft.com/Reporting/v13/GenerateReport/Poll*' => Http::response([
                'ReportRequestStatus' => ['Status' => 'Error'],
            ], 200),
            'campaign.api.bingads.microsoft.com/*' => Http::response(['ReportRequestId' => 'req-1'], 200),
        ]);

        // On s'arrête volontairement au premier appel : le contrat qui compte
        // ici est l'obtention du jeton Microsoft (en-tête client_secret, scope
        // explicite), pas le flux de rapport complet.
        try {
            (new MicrosoftAdsConnector)->fetchDailyMetrics(
                ['client_id' => 'a', 'client_secret' => 'b', 'refresh_token' => 'c', 'customer_id' => '123'],
                new \DateTimeImmutable('2026-10-20'),
                new \DateTimeImmutable('2026-10-20'),
            );
        } catch (\Throwable) {
            // Un échec plus loin (poll sans statut) est acceptable : ce test
            // n'affirme rien sur le flux asynchrone, seulement sur l'échange.
        }

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            return str_contains($request->url(), 'login.microsoftonline.com')
                && $request->hasHeader('client_secret')
                && ($request['grant_type'] ?? null) === 'refresh_token'
                && ($request['scope'] ?? null) === 'https://ads.microsoft.com/ads.manage offline_access';
        });
    }
}
