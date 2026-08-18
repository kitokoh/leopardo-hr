<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Issue #1774 — ThrottleRequests résilient aux pannes du stockage du compteur.
 *
 * Contexte : lors d'une rafale, la prod répondait 500 (« Server Error ») sur
 * quasi toutes les routes throttlées quand la lecture/écriture du compteur
 * échouait (cache Redis/Upstash saturé ou injoignable) : l'exception remontait
 * depuis le RateLimiter à travers le middleware.
 *
 * Comportement :
 *  - échec du stockage pendant la PHASE LIMITER (lecture/écriture du compteur)
 *    → `report()` (Sentry/logs) + **429 dégradé** avec `Retry-After` (au lieu
 *    d'un 500) pour les limiters NOMÉS (politiques métier fail-closed) ;
 *    → fail-open (pass-through, 200) pour les limites ANONYMES `throttle:X,Y`
 *    (anti-abus générique best-effort, ex. /health — les sondes doivent
 *    répondre 200/503 même pendant la panne du compteur, cf. #1774/#5034).
 *  - échec pendant la pose des headers `X-RateLimit-*` (phase post-`$next()`)
 *    → `report()` non bloquant, la réponse du contrôleur est conservée.
 *  - dépassement réel du quota → 429 normal (`ThrottleRequestsException`
 *    re-lancée, headers conservés) ; exceptions du pipeline en aval (contrôleur)
 *    → jamais masquées (`$next()` s'exécute HORS du try/catch).
 */
class ResilientThrottleRequests extends ThrottleRequests
{
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = ''): Response
    {
        // ── Phase limiter : résolution des limites. Un échec de la résolution
        //    (limiter nommé qui plante, ex. dépendance en panne) → 429 dégradé.
        try {
            $limits = $this->resolveLimits($request, $maxAttempts, (int) $decayMinutes, $prefix);
        } catch (ThrottleRequestsException|HttpResponseException $e) {
            // Réponse custom du limiter : comportement nominal inchangé.
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return $this->degradedTooManyRequestsResponse($request);
        }

        if ($limits instanceof Response) {
            // Le limiter nommé a directement retourné une réponse.
            return $limits;
        }

        if ($limits === null) {
            // Limit::none() : route non throttlée — pas de compteur à vérifier.
            // $next() s'exécute HORS de tout try : une exception du pipeline en
            // aval ne doit jamais être convertie en 429 dégradé.
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        // ── Phase limiter : vérification + incrément du compteur (HORS $next).
        //    Tout échec ici (stockage du compteur indisponible) → 429 dégradé.
        try {
            foreach ($limits as $limit) {
                if ($this->limiter->tooManyAttempts($limit->key, $limit->maxAttempts)) {
                    throw $this->buildException($request, $limit->key, $limit->maxAttempts, $limit->responseCallback);
                }

                if ($limit->afterCallback === null) {
                    $this->limiter->hit($limit->key, $limit->decaySeconds);
                }
            }
        } catch (ThrottleRequestsException|HttpResponseException $e) {
            // Dépassement réel du quota (ou réponse custom du limiter) :
            // comportement nominal inchangé.
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            // Panne du stockage du compteur sur une limite ANONYME (throttle:X,Y
            // générique, ex. /health) : fail-open — la politique ne peut de toute
            // façon pas être appliquée, et 429-er les sondes/probes amplifierait
            // la panne (déploiements Render, supervision externe). Les limiters
            // NOMÉS (auth-sensitive, payroll-sensitive, platform-sensitive...)
            // restent fail-closed : 429 dégradé (voir #1774/#4955).
            $namedOnly = true;
            foreach ($limits as $limit) {
                if (! $limit->named) {
                    $namedOnly = false;
                    break;
                }
            }

            if (! $namedOnly) {
                $response = $next($request);

                return $response;
            }

            return $this->degradedTooManyRequestsResponse($request);
        }

        // ── Pipeline applicatif : les exceptions du contrôleur remontent
        //    telles quelles (jamais converties en 429).
        /** @var Response $response */
        $response = $next($request);

        // ── Phase headers : une panne du stockage n'invalide pas la réponse.
        try {
            foreach ($limits as $limit) {
                if ($limit->afterCallback !== null && ($limit->afterCallback)($response)) {
                    $this->limiter->hit($limit->key, $limit->decaySeconds);
                }

                $response = $this->addHeaders(
                    $response,
                    $limit->maxAttempts,
                    $this->calculateRemainingAttempts($limit->key, $limit->maxAttempts)
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }

    /**
     * Résout les limites (nommées ou `throttle:X,Y`) comme le fait
     * `ThrottleRequests::handle()`. Retourne :
     *  - `null` quand le limiter est `Limit::none()` (pas de throttling) ;
     *  - un objet `Response` quand le limiter retourne directement une réponse ;
     *  - un tableau de `ThrottleLimitConfig` sinon.
     *
     * @return array<int, ThrottleLimitConfig>|Response|null
     */
    private function resolveLimits(Request $request, mixed $maxAttempts, int $decayMinutes, string $prefix): array|Response|null
    {
        if (! is_int($maxAttempts) && ! is_string($maxAttempts)) {
            $maxAttempts = 60;
        }

        if (is_string($maxAttempts)
            && func_num_args() === 4
            && ! is_null($limiter = $this->limiter->limiter($maxAttempts))) {
            return $this->resolveNamedLimits($request, (string) $maxAttempts, $limiter);
        }

        return [
            new ThrottleLimitConfig(
                key: $prefix.$this->resolveRequestSignature($request),
                maxAttempts: (int) $this->resolveMaxAttempts($request, $maxAttempts),
                decaySeconds: 60 * $decayMinutes,
                // Limite anonyme (throttle:X,Y) : anti-abus générique best-effort.
                named: false,
            ),
        ];
    }

    /**
     * @return array<int, ThrottleLimitConfig>|Response|null
     */
    private function resolveNamedLimits(Request $request, string $limiterName, Closure $limiter): array|Response|null
    {
        $limiterResponse = $limiter($request);

        if ($limiterResponse instanceof Response) {
            return $limiterResponse;
        }

        if ($limiterResponse instanceof Unlimited) {
            return null;
        }

        $limiterNameKey = $limiterName;

        /** @var Limit|array<int, Limit> $limitResponse */
        $limitResponse = $limiterResponse;
        if ($limitResponse instanceof Limit) {
            $limitResponse = [$limitResponse];
        }

        $configs = [];
        foreach ($limitResponse as $limit) {
            $configs[] = new ThrottleLimitConfig(
                key: self::$shouldHashKeys
                    ? md5($limiterNameKey.$this->normalizeKey($limit->key))
                    : $limiterNameKey.':'.$this->normalizeKey($limit->key),
                maxAttempts: (int) $limit->maxAttempts,
                decaySeconds: (int) $limit->decaySeconds,
                afterCallback: $limit->afterCallback instanceof Closure ? $limit->afterCallback : null,
                responseCallback: $limit->responseCallback instanceof Closure ? $limit->responseCallback : null,
                named: true,
            );
        }

        return $configs;
    }

    /**
     * Normalise la clé de limite (documentée `mixed` sur `Limit`) en chaîne.
     */
    private function normalizeKey(mixed $key): string
    {
        if (is_string($key) || is_int($key) || is_float($key) || is_bool($key)) {
            return (string) $key;
        }

        return '';
    }

    /**
     * 429 dégradé : le stockage du compteur est indisponible, on ne peut pas
     * vérifier le quota — on limite (fail-closed) plutôt que de planter en 500.
     */
    private function degradedTooManyRequestsResponse(Request $request): JsonResponse
    {
        // Issue #4955 : le 429 dégradé (panne du stockage du compteur) doit
        // suivre le même contrat que le reste de l'API — code stable +
        // localized_message via le catalogue errors.* (cf. renderer #3810/#4689).
        return new JsonResponse(
            [
                // Code distinct pour l'observabilité (stockage du compteur KO,
                // pas un vrai dépassement de quota) — message localisé quand même.
                'error' => 'TOO_MANY_REQUESTS_DEGRADED',
                'message' => 'TOO_MANY_REQUESTS_DEGRADED',
                'localized_message' => __('errors.TOO_MANY_REQUESTS', [], $request->getLocale()),
            ],
            429,
            ['Retry-After' => 60]
        );
    }
}
