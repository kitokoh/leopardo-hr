<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Infrastructure\Services;

use App\Modules\SearchIntelligence\Domain\Contracts\PageFetcherInterface;
use Illuminate\Support\Facades\Http;

/**
 * GSI-2 (#8513) — implémentation HTTP du fetcher, en **lecture seule**.
 *
 * Contraintes volontairement basses et explicites :
 *  - timeout court (le scanner balaie un parc, il ne doit pas s'enliser sur une
 *    page morte) ;
 *  - `User-Agent` identifiable : on ne se fait pas passer pour un navigateur,
 *    ce qui permet à la vitrine de nous exclure de ses stats si elle le
 *    souhaite ;
 *  - redirections suivies, mais l'URL FINALE est retournée à l'appelant pour
 *    qu'une redirection en chaîne soit détectée comme une canonical divergente
 *    plutôt que silencieusement acceptée.
 *
 * Aucune méthode d'écriture n'existe : le contrat ne l'expose pas.
 */
final class HttpPageFetcher implements PageFetcherInterface
{
    public function __construct(
        private readonly int $timeoutSeconds = 10,
    ) {}

    public function fetch(string $url): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'LeopardoSearchIntel/1.0 (+https://leopardo-rh.com)',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
                ->timeout($this->timeoutSeconds)
                ->withOptions(['allow_redirects' => ['max' => 5]])
                ->get($url);
        } catch (\Throwable) {
            // DNS, TLS, timeout : la page n'a pas été observée. `null` est
            // distinct d'un 404, qui reste une réponse réelle.
            return null;
        }

        return [
            'status' => $response->status(),
            'body' => $response->body(),
        ];
    }
}
