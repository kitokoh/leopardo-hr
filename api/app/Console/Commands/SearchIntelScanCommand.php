<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\SearchIntelligence\Infrastructure\Services\SeoPageScanner;
use Illuminate\Console\Command;

/**
 * GSI-12 (#8523) point 4 — scan SEO planifié.
 *
 * Pourquoi une commande plutôt qu'un appel direct depuis le scheduler : la
 * commande est rejouable à la main (`php artisan search-intel:scan`), ce qui
 * est indispensable le jour où le scan planifié échoue en production et qu'il
 * faut diagnostiquer sans attendre 24 h.
 *
 * Deux protections contre les effets de bord d'un scan automatique :
 *
 * - **Sans cible déclarée, la commande ne fait rien et sort en succès.** Un
 *   scan qui devine un domaine enverrait du trafic inattendu sur un site tiers.
 * - **Les pages injoignables ne sont jamais comptées comme cassées** : le
 *   scanner renvoie `null` pour une panne réseau, distinct d'un 404 observé.
 *
 * La commande n'écrit rien d'autre que ce que le scanner persiste (issues
 * SEO) ; elle est idempotente pour la même journée, car le scanner déduplique
 * par page + type d'issue.
 */
final class SearchIntelScanCommand extends Command
{
    protected $signature = 'search-intel:scan
                            {--url=* : URL explicite à scanner (au lieu des cibles configurées)}';

    protected $description = 'Scan SEO planifié des pages déclarées (lecture seule sur la production)';

    public function handle(SeoPageScanner $scanner): int
    {
        /** @var list<string> $explicit */
        $explicit = array_values(array_filter(
            (array) $this->option('url'),
            static fn ($url): bool => is_string($url) && $url !== '',
        ));

        $targets = $explicit !== [] ? $explicit : $this->configuredTargets();

        if ($targets === []) {
            $this->info('Aucune cible configurée (SEARCH_INTEL_SCAN_BASE_URL / SEARCH_INTEL_SCAN_PATHS) — rien à scanner.');

            return self::SUCCESS;
        }

        $scanned = 0;
        $issues = 0;
        $unreachable = [];

        foreach ($targets as $url) {
            $result = $scanner->scan($url);

            if ($result === null) {
                $unreachable[] = $url;

                continue;
            }

            $scanned++;
            $issues += count($result['analysis']->issues);
        }

        $this->info(sprintf('Scan SEO : %d page(s) lue(s), %d issue(s), %d injoignable(s).', $scanned, $issues, count($unreachable)));

        if ($unreachable !== []) {
            foreach ($unreachable as $url) {
                $this->warn('  injoignable : '.$url);
            }
        }

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function configuredTargets(): array
    {
        $baseUrl = config('search_intel.scan.base_url');
        $paths = config('search_intel.scan.paths', []);

        if (! is_string($baseUrl) || $baseUrl === '' || ! is_array($paths)) {
            return [];
        }

        $out = [];

        foreach ($paths as $path) {
            if (! is_string($path)) {
                continue;
            }

            $out[] = rtrim($baseUrl, '/').'/'.ltrim($path, '/');
        }

        return $out;
    }
}
