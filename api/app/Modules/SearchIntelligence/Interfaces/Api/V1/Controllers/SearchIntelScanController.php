<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers;

use App\Modules\SearchIntelligence\Infrastructure\Services\SeoPageScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GSI-2 (#8513) — déclenchement d'un scan SEO.
 *
 * `POST /api/v1/platform/search-intel/seo/scan` — garde
 * `platform.permission:search_intel.manage`.
 *
 * Le scan est en LECTURE SEULE : il balaie des URL de la vitrine et écrit son
 * observation en base. Il ne modifie jamais le site. C'est ce que dit
 * explicitement le critère d'acceptation de l'issue, et c'est garanti par le
 * contrat `PageFetcherInterface` qui n'expose aucune méthode d'écriture.
 *
 * Cible : soit des `urls` explicites, soit les `paths` configurés
 * (`search_intel.scan.paths`) résolus contre `search_intel.scan.base_url`.
 * Sans cible déclarée → 422 : le scanner ne devine jamais un domaine.
 */
final class SearchIntelScanController
{
    public function __construct(
        private readonly SeoPageScanner $scanner,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'urls' => ['sometimes', 'array', 'max:50'],
            'urls.*' => ['string', 'url', 'max:2048'],
        ]);

        /** @var list<string> $targets */
        $targets = $validated['urls'] ?? $this->configuredTargets();

        if ($targets === []) {
            return new JsonResponse([
                'error' => [
                    'code' => 'scan_target_missing',
                    'message' => 'Aucune cible : renseignez `urls` ou SEARCH_INTEL_SCAN_BASE_URL.',
                ],
            ], 422);
        }

        $scanned = 0;
        $unreachable = [];
        $issuesFound = 0;

        foreach ($targets as $url) {
            $result = $this->scanner->scan($url);

            if ($result === null) {
                // Page non observée (réseau) : on ne la déclare pas cassée.
                $unreachable[] = $url;

                continue;
            }

            $scanned++;
            $issuesFound += count($result['analysis']->issues);
        }

        return new JsonResponse([
            'data' => [
                'scanned' => $scanned,
                'issues_found' => $issuesFound,
                'unreachable' => $unreachable,
            ],
        ]);
    }

    /**
     * Cibles par défaut : `base_url` + `paths`. Base absente → liste vide, ce
     * qui produit un 422 explicite plutôt qu'un scan d'un domaine inconnu.
     *
     * @return list<string>
     */
    private function configuredTargets(): array
    {
        $base = trim((string) config('search_intel.scan.base_url', ''));

        if ($base === '') {
            return [];
        }

        $base = rtrim($base, '/');
        $targets = [];

        /** @var array<int, string> $paths */
        $paths = (array) config('search_intel.scan.paths', ['/']);

        foreach ($paths as $path) {
            $targets[] = $base.'/'.ltrim($path, '/');
        }

        return $targets;
    }
}
