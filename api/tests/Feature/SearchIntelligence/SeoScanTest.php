<?php

declare(strict_types=1);

namespace Tests\Feature\SearchIntelligence;

use App\Modules\SearchIntelligence\Domain\Contracts\PageFetcherInterface;
use App\Modules\SearchIntelligence\Domain\Enums\SeoIssueStatus;
use App\Modules\SearchIntelligence\Domain\Enums\SeoIssueType;
use App\Modules\SearchIntelligence\Domain\Models\SeoIssue;
use App\Modules\SearchIntelligence\Domain\Models\SeoPage;
use App\Modules\SearchIntelligence\Infrastructure\Services\SeoPageScanner;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * GSI-2 (#8513) — scanner SEO : règles réelles, idempotence, erreur HTTP.
 *
 * Le fetcher est remplacé par un stub en mémoire : aucun accès réseau, et
 * surtout aucune dépendance à un serveur externe pour une suite de tests qui
 * doit rester déterministe en CI.
 */
final class SeoScanTest extends TestCase
{
    use RefreshTenantDatabase;

    private const GOOD_HTML = <<<'HTML'
        <html><head>
        <title>Leopardo RH — gestion de la paie</title>
        <meta name="description" content="La paie et les RH pour les PME.">
        <link rel="canonical" href="https://leopardo-rh.com/">
        <script type="application/ld+json">{"@type":"Organization"}</script>
        </head><body><h1>Bienvenue</h1></body></html>
        HTML;

    private const BAD_HTML = <<<'HTML'
        <html><head></head><body><p>Page vide</p></body></html>
        HTML;

    public function test_healthy_page_produces_no_issue(): void
    {
        $this->bindFetcher(['https://leopardo-rh.com/' => [200, self::GOOD_HTML]]);

        $result = $this->scanner()->scan('https://leopardo-rh.com/');

        self::assertNotNull($result);
        self::assertTrue($result['analysis']->isHealthy());
        self::assertSame(0, SeoIssue::query()->count());
    }

    public function test_bare_page_produces_the_expected_issues(): void
    {
        $this->bindFetcher(['https://leopardo-rh.com/vide' => [200, self::BAD_HTML]]);

        $result = $this->scanner()->scan('https://leopardo-rh.com/vide');

        self::assertNotNull($result);

        // Titre, meta description, H1 et JSON-LD manquent ; la canonical est
        // ABSENTE, ce qui n'est pas une anomalie (beaucoup de sites sains
        // n'en déclarent pas).
        self::assertTrue($result['analysis']->has(SeoIssueType::TitleMissing));
        self::assertTrue($result['analysis']->has(SeoIssueType::MetaDescriptionMissing));
        self::assertTrue($result['analysis']->has(SeoIssueType::H1Missing));
        self::assertTrue($result['analysis']->has(SeoIssueType::MissingStructuredData));
        self::assertFalse($result['analysis']->has(SeoIssueType::CanonicalDivergent));

        // Chaque anomalie est persistée au statut `detected`, avec sa
        // correction proposée — pas seulement un constat.
        $issue = SeoIssue::query()->where('type', SeoIssueType::TitleMissing->value)->firstOrFail();
        self::assertSame(SeoIssueStatus::Detected, $issue->status);
        self::assertNotNull($issue->recommendation);
    }

    public function test_scan_is_idempotent_on_the_same_url(): void
    {
        $this->bindFetcher(['https://leopardo-rh.com/vide' => [200, self::BAD_HTML]]);

        $this->scanner()->scan('https://leopardo-rh.com/vide');
        $issuesAfterFirst = SeoIssue::query()->count();
        $pagesAfterFirst = SeoPage::query()->count();

        $this->scanner()->scan('https://leopardo-rh.com/vide');

        // Re-scanner ne duplique NI la page NI les anomalies.
        self::assertSame($pagesAfterFirst, SeoPage::query()->count());
        self::assertSame($issuesAfterFirst, SeoIssue::query()->count());
    }

    public function test_fragment_does_not_create_a_second_page(): void
    {
        $this->bindFetcher([
            'https://leopardo-rh.com/a' => [200, self::GOOD_HTML],
            'https://leopardo-rh.com/a#top' => [200, self::GOOD_HTML],
        ]);

        $this->scanner()->scan('https://leopardo-rh.com/a');
        $this->scanner()->scan('https://leopardo-rh.com/a#top');

        // Même URL à la normalisation près : une seule page en base.
        self::assertSame(1, SeoPage::query()->count());
    }

    public function test_http_error_is_critical_and_suppresses_other_rules(): void
    {
        $this->bindFetcher(['https://leopardo-rh.com/casse' => [404, '<html></html>']]);

        $result = $this->scanner()->scan('https://leopardo-rh.com/casse');

        self::assertNotNull($result);
        // Une 404 ne se voit pas reprocher un titre manquant : la page n'est
        // de toute façon pas exploitable.
        self::assertSame([SeoIssueType::HttpError], $result['analysis']->issues);

        $issue = SeoIssue::query()->firstOrFail();
        self::assertSame('critical', $issue->severity->value);
    }

    public function test_unreachable_page_writes_nothing(): void
    {
        // Pas d'entrée dans le stub → fetch() renvoie null.
        $this->bindFetcher([]);

        $result = $this->scanner()->scan('https://leopardo-rh.com/injoignable');

        self::assertNull($result);
        // Un timeout local ne doit pas être enregistré comme « page cassée ».
        self::assertSame(0, SeoPage::query()->count());
        self::assertSame(0, SeoIssue::query()->count());
    }

    public function test_applied_issue_disappearing_on_rescan_becomes_verified(): void
    {
        $this->bindFetcher(['https://leopardo-rh.com/vide' => [200, self::BAD_HTML]]);
        $this->scanner()->scan('https://leopardo-rh.com/vide');

        $issue = SeoIssue::query()->where('type', SeoIssueType::TitleMissing->value)->firstOrFail();
        $issue->status = SeoIssueStatus::Applied;
        $issue->save();

        // La correction a été appliquée côté site : le re-scan ne retrouve
        // plus l'anomalie.
        $this->bindFetcher(['https://leopardo-rh.com/vide' => [200, self::GOOD_HTML]]);
        $this->scanner()->scan('https://leopardo-rh.com/vide');

        $issue->refresh();
        self::assertSame(SeoIssueStatus::Verified, $issue->status);
        self::assertNotNull($issue->verified_at);
    }

    /**
     * @param  array<string, array{0: int, 1: string}>  $responses
     */
    private function bindFetcher(array $responses): void
    {
        $this->app->instance(PageFetcherInterface::class, new class($responses) implements PageFetcherInterface
        {
            /** @param array<string, array{0: int, 1: string}> $responses */
            public function __construct(private readonly array $responses) {}

            public function fetch(string $url): ?array
            {
                if (! isset($this->responses[$url])) {
                    return null;
                }

                return ['status' => $this->responses[$url][0], 'body' => $this->responses[$url][1]];
            }
        });
    }

    private function scanner(): SeoPageScanner
    {
        return $this->app->make(SeoPageScanner::class);
    }
}
