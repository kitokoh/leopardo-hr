<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RTMX (#5277) — GET conditionnels (ETag / 304) pour les réponses JSON de l'API.
 *
 * Pose un ETag fort (sha1 du corps) + une politique de cache privée et répond
 * 304 Not Modified quand le client envoie un If-None-Match correspondant.
 * Les lectures répétées du pointage mobile (`/attendance/today`,
 * `/attendance/config`, `/me/*`, rapports) ne retransmettent plus le corps
 * tant qu'il n'a pas changé — socle plateforme du « pointage < 3 s ».
 *
 * Sûreté :
 * - ETag recalculé sur le corps EXACT de la réponse courante : un 304 ne peut
 *   jamais être servi pour un contenu différent (pas de cache partagé, cache
 *   privé uniquement, `must-revalidate`) ;
 * - aucune politique de cache explicite posée par l'application n'est écrasée ;
 * - seules les réponses JSON 2xx GET sont concernées (les écritures et les
 *   erreurs ne sont jamais cachées).
 *
 * Piège Symfony (HTTP Foundation) : `ResponseHeaderBag` calcule TOUJOURS une
 * politique de cache — `no-cache, private` par défaut, ou les directives
 * posées par l'app suffixées de `, private` quand elles ne contiennent ni
 * `public` ni `private`. Le header `Cache-Control` n'est donc JAMAIS absent en
 * sortie de pipeline : tester sa présence reviendrait à ne jamais activer le
 * middleware. On ne considère comme politique EXPLICITE (à respecter sans
 * ETag) que ce qui diffère du défaut calculé par Symfony.
 */
class HttpCacheMiddleware
{
    private const CACHE_CONTROL = 'private, max-age=0, must-revalidate';

    /**
     * Politique calculée par Symfony pour une réponse sans directives
     * explicites (ResponseHeaderBag::computeCacheControlValue()).
     */
    private const SYMFONY_DEFAULT_CACHE_CONTROL = 'no-cache, private';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $request->isMethod('GET')) {
            return $response;
        }

        if (! $response instanceof JsonResponse || ! $response->isSuccessful()) {
            return $response;
        }

        // Un ETag déjà posé (app ou autre middleware) fait foi.
        if ($response->headers->has('ETag')) {
            return $response;
        }

        // Politique de cache explicite posée par l'application → on ne pose
        // pas d'ETag et on n'écrase rien (le défaut calculé par Symfony n'est
        // PAS une politique explicite : il s'applique à toute réponse sans
        // directives — on le remplace par notre politique privée).
        $cacheControl = $response->headers->get('Cache-Control');
        if (is_string($cacheControl) && trim($cacheControl) !== self::SYMFONY_DEFAULT_CACHE_CONTROL) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || $content === '') {
            return $response;
        }

        $etag = '"'.sha1($content).'"';

        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', self::CACHE_CONTROL);
        $response->headers->set('Vary', $this->mergeVary($response->headers->get('Vary')));

        $ifNoneMatch = $request->headers->get('If-None-Match');
        if (is_string($ifNoneMatch) && $ifNoneMatch !== '' && $this->etagMatches($ifNoneMatch, $etag)) {
            // 304 déterministe : corps vide, ETag + politique conservés.
            return new Response('', Response::HTTP_NOT_MODIFIED, [
                'ETag' => $etag,
                'Cache-Control' => self::CACHE_CONTROL,
                'Vary' => $this->mergeVary($response->headers->get('Vary')),
            ]);
        }

        return $response;
    }

    /**
     * Comparaison If-None-Match (RFC 9110 §13.1.2) : `*` ou liste d'ETags.
     * La comparaison affaiblie (`W/`) est tolérée sans risque : l'ETag est
     * recalculé sur le corps courant, un match implique un corps identique.
     */
    private function etagMatches(string $ifNoneMatch, string $etag): bool
    {
        if (trim($ifNoneMatch) === '*') {
            return true;
        }

        foreach (explode(',', $ifNoneMatch) as $candidate) {
            $candidate = trim($candidate);
            if (str_starts_with($candidate, 'W/')) {
                $candidate = substr($candidate, 2);
            }

            if ($candidate === $etag) {
                return true;
            }
        }

        return false;
    }

    private function mergeVary(?string $current): string
    {
        if ($current === null || trim($current) === '') {
            return 'Accept-Encoding';
        }

        $values = array_map('trim', explode(',', $current));
        if (! in_array('Accept-Encoding', $values, true)) {
            $values[] = 'Accept-Encoding';
        }

        return implode(', ', $values);
    }
}
