<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Infrastructure\Services;

use App\Modules\SearchIntelligence\Domain\Contracts\PageFetcherInterface;
use App\Rules\NotPrivateUrl;
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
 *  - redirections suivies MANUELLEMENT, une par une, avec revalidation de
 *    l'hôte à chaque saut (voir #9348 ci-dessous).
 *
 * Aucune méthode d'écriture n'existe : le contrat ne l'expose pas.
 *
 * ## #9348 (audit sécurité 2026-11-10, F6) — garde anti-SSRF
 *
 * Ce fetcher était le SEUL point de sortie du dépôt à ne pas passer par
 * `NotPrivateUrl` : `Http::get($url)` sur une URL arbitraire, avec
 * `allow_redirects => ['max' => 5]` laissé à Guzzle. Deux conséquences :
 *
 *  1. un compte plateforme compromis (la route exige `search_intel.manage`)
 *     pouvait sonder le réseau interne et le point de métadonnées cloud
 *     (`169.254.169.254`) ;
 *  2. une redirection `302 → http://10.0.0.1/` contournait un contrôle qui
 *     n'aurait porté que sur l'URL initiale.
 *
 * Le suivi automatique est donc DÉSACTIVÉ : chaque saut est résolu ici, son
 * hôte revalidé par `NotPrivateUrl::isPublicHost()` (qui re-résout le DNS,
 * donc couvre aussi le rebinding), et le schéma doit rester `https`.
 */
final class HttpPageFetcher implements PageFetcherInterface
{
    /** Nombre maximal de redirections suivies (même budget qu'avant). */
    private const MAX_REDIRECTS = 5;

    public function __construct(
        private readonly int $timeoutSeconds = 10,
    ) {}

    public function fetch(string $url): ?array
    {
        $current = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (! $this->isAllowedTarget($current)) {
                // Cible interne, schéma non https, ou hôte non résolvable :
                // on n'émet AUCUNE requête (fail-closed).
                return null;
            }

            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'LeopardoSearchIntel/1.0 (+https://leopardo-rh.com)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                    ->timeout($this->timeoutSeconds)
                    // #9348 — suivi MANUEL : Guzzle ne doit jamais décider
                    // seul d'aller voir une autre adresse.
                    ->withOptions(['allow_redirects' => false])
                    ->get($current);
            } catch (\Throwable) {
                // DNS, TLS, timeout : la page n'a pas été observée. `null` est
                // distinct d'un 404, qui reste une réponse réelle.
                return null;
            }

            if (! $response->redirect()) {
                return [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ];
            }

            $location = $response->header('Location');

            if (! is_string($location) || $location === '') {
                // Redirection sans `Location` : rien à suivre, on rend la
                // réponse telle quelle (le statut 3xx est une observation).
                return [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ];
            }

            $current = $this->resolveLocation($current, $location);
        }

        // Budget de redirections épuisé : la page n'a pas été observée.
        return null;
    }

    /**
     * #9348 — une cible n'est autorisée que si elle est en `https://` et que
     * son hôte résout exclusivement vers des adresses publiques.
     */
    private function isAllowedTarget(string $url): bool
    {
        if (! str_starts_with(strtolower($url), 'https://')) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        return NotPrivateUrl::isPublicHost($host);
    }

    /**
     * #9348 — résout une valeur `Location` (absolue ou relative) contre
     * l'URL courante, sans dépendre de Guzzle.
     */
    private function resolveLocation(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($base);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $location;
        }

        $origin = $parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '//')) {
            return $parts['scheme'].':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $directory = isset($parts['path']) ? rtrim(dirname($parts['path']), '/') : '';

        return $origin.$directory.'/'.$location;
    }
}
