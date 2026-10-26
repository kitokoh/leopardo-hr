<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Infrastructure\Services;

use App\Modules\SearchIntelligence\Domain\Contracts\PageFetcherInterface;
use App\Modules\SearchIntelligence\Domain\Enums\SeoIssueStatus;
use App\Modules\SearchIntelligence\Domain\Enums\SeoIssueType;
use App\Modules\SearchIntelligence\Domain\Models\SeoIssue;
use App\Modules\SearchIntelligence\Domain\Models\SeoPage;
use App\Modules\SearchIntelligence\Domain\ValueObjects\PageAnalysis;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * GSI-2 (#8513) — scanner technique du parc, en LECTURE SEULE.
 *
 * Trois propriétés, dans l'ordre où elles comptent :
 *
 *  1. **Il ne modifie jamais la production.** Il n'a pas de méthode d'écriture
 *     distante : le seul contrat disponible (`PageFetcherInterface`) n'expose
 *     que `GET`. Les corrections sont *proposées* (statut `recommended`) et
 *     appliquées par un humain dans le CMS — hors de ce module.
 *
 *  2. **Il est idempotent.** Re-scanner le même jour ne crée ni doublon de
 *     page ni doublon d'anomalie :
 *       - page : `updateOrCreate` sur `url_hash` ;
 *       - anomalie : une seule ligne par (page, type) NON TERMINALE. Si
 *         l'anomalie est déjà `verified`/`dismissed` et que le scan la
 *         retrouve, elle est ROUVERTE (la correction n'a pas tenu) — c'est le
 *         chemin `applied → detected` de la machine à états.
 *
 *  3. **Il ne fait pas de bruit.** Une anomalie qui a disparu entre deux scans
 *     n'est pas supprimée : elle reste dans l'historique, elle sort simplement
 *     de la file (`verified` si elle était `applied`, sinon on ne la touche
 *     pas — un humain n'a rien fait, il n'y a rien à vérifier).
 */
final class SeoPageScanner
{
    public function __construct(
        private readonly PageFetcherInterface $fetcher,
        private readonly SeoPageAnalyzer $analyzer,
    ) {}

    /**
     * Scanne une URL et persiste le résultat.
     *
     * @return array{page: SeoPage, analysis: PageAnalysis}|null `null` si la
     *         page n'a pas pu être récupérée (réseau) — dans ce cas AUCUNE
     *         écriture n'a lieu : on ne déclare pas « page cassée » pour un
     *         timeout local.
     */
    public function scan(string $url): ?array
    {
        $response = $this->fetcher->fetch($url);

        if ($response === null) {
            return null;
        }

        $analysis = $this->analyzer->analyze($url, $response['status'], $response['body']);

        return DB::transaction(function () use ($analysis): array {
            $page = $this->persistPage($analysis);
            $this->syncIssues($page, $analysis);

            return ['page' => $page, 'analysis' => $analysis];
        });
    }

    /** Upsert de la page sur `url_hash` : idempotent par construction. */
    private function persistPage(PageAnalysis $analysis): SeoPage
    {
        $parts = parse_url($analysis->url);
        $host = is_array($parts) && isset($parts['host']) ? $parts['host'] : '';
        $path = is_array($parts) && isset($parts['path']) ? $parts['path'] : '/';

        /** @var SeoPage $page */
        $page = SeoPage::query()->updateOrCreate(
            ['url_hash' => SeoPage::hashFor($analysis->url)],
            [
                'url' => $analysis->url,
                'host' => $host,
                'path' => $path,
                'title' => $analysis->title,
                'meta_description' => $analysis->metaDescription,
                'http_status' => $analysis->httpStatus,
                'word_count' => $this->wordCount($analysis->title, $analysis->metaDescription, $analysis->h1),
                'canonical_url' => $analysis->canonical,
                'has_schema' => $analysis->hasStructuredData,
                'is_indexable' => $analysis->httpStatus < 400,
                'last_scanned_at' => Carbon::now(),
            ],
        );

        return $page;
    }

    /** Crée / met à jour une anomalie par (page, type) non terminale. */
    private function syncIssues(SeoPage $page, PageAnalysis $analysis): void
    {
        foreach ($analysis->issues as $type) {
            $existing = SeoIssue::query()
                ->where('seo_page_id', $page->id)
                ->where('type', $type->value)
                ->orderByDesc('id')
                ->first();

            if ($existing instanceof SeoIssue) {
                // Anomalie encore dans la file : simple rafraîchissement, on
                // ne fait pas reculer son statut (un humain a pu l'approuver).
                if (! $existing->status->isTerminal()) {
                    continue;
                }

                // Déjà `verified` et toujours présente : la correction n'a pas
                // tenu → réouverture explicite.
                $existing->status = SeoIssueStatus::Detected;
                $existing->recommendation = $type->recommendation();
                $existing->detected_at = Carbon::now();
                $existing->save();

                continue;
            }

            SeoIssue::query()->create([
                'seo_page_id' => $page->id,
                'type' => $type->value,
                'severity' => $type->defaultSeverity()->value,
                'status' => SeoIssueStatus::Detected->value,
                'recommendation' => $type->recommendation(),
                'title' => $type->label(),
                'detail' => 'Détectée lors du scan de '.$analysis->url,
                'detected_at' => Carbon::now(),
            ]);
        }

        $this->closeVanishedIssues($page, $analysis);
    }

    /**
     * Une anomalie `applied` qui n'apparaît PLUS au re-scan est `verified` :
     * c'est le critère d'acceptation « re-scan post-application qui fait
     * disparaître l'issue → verified ».
     */
    private function closeVanishedIssues(SeoPage $page, PageAnalysis $analysis): void
    {
        $stillPresent = array_map(
            static fn (SeoIssueType $type): string => $type->value,
            $analysis->issues,
        );

        $applied = SeoIssue::query()
            ->where('seo_page_id', $page->id)
            ->where('status', SeoIssueStatus::Applied->value)
            ->get();

        foreach ($applied as $issue) {
            if (in_array($issue->type, $stillPresent, true)) {
                continue;
            }

            $issue->status = SeoIssueStatus::Verified;
            $issue->resolved_at = Carbon::now();
            $issue->verified_at = Carbon::now();
            $issue->save();
        }
    }

    private function wordCount(?string ...$parts): int
    {
        $text = trim(implode(' ', array_map(static fn (?string $p): string => (string) $p, $parts)));

        if ($text === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $text) ?: []);
    }
}
