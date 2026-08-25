<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Exceptions\DomainException;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * RTMX (#5277) — rejeu sûr des écritures API (POST/PUT/PATCH).
 *
 * Quand le client envoie `Idempotency-Key` + `Authorization`, la première
 * réponse 2xx est mémorisée 24 h puis rejouée à l'identique pour toute
 * retentative avec la même clé et le même corps (header
 * `Idempotent-Replayed: true`). La file hors-ligne mobile
 * (`offline_punches` + `OfflineSyncService`) peut donc rejouer un pointage
 * (check-in / check-out / geo-events) après une coupure réseau sans créer de
 * doublon ni de faux rejet.
 *
 * Sûreté :
 * - la clé de cache est scopée par token (sha1 du header Authorization) + clé
 *   client + signature méthode/URI/corps → aucune relecture inter-utilisateur,
 *   aucune fausse relecture pour un corps différent ;
 * - les requêtes anonymes (sans Authorization) ne sont jamais dédupliquées ;
 * - seules les réponses JSON 2xx sont mémorisées ; le verrou anti-course est
 *   toujours libéré (finally) même en cas d'exception.
 */
class IdempotencyMiddleware
{
    private const TTL_SECONDS = 86400; // 24 h

    private const LOCK_TTL_SECONDS = 60;

    private const KEY_PATTERN = '/^[A-Za-z0-9._:-]{8,255}$/';

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $idempotencyKey = $request->headers->get('Idempotency-Key');
        $authorization = $request->headers->get('Authorization');

        if (! is_string($idempotencyKey) || $idempotencyKey === '' || ! is_string($authorization) || $authorization === '') {
            return $next($request);
        }

        if (! preg_match(self::KEY_PATTERN, $idempotencyKey)) {
            throw new DomainException(
                'INVALID_IDEMPOTENCY_KEY',
                422,
                'INVALID_IDEMPOTENCY_KEY'
            );
        }

        $cacheKey = $this->cacheKey($request, $idempotencyKey, $authorization);

        $cached = Cache::get($cacheKey);
        if ($this->isSnapshot($cached)) {
            return $this->replay($cached);
        }

        $lockKey = $cacheKey.':lock';
        if (! Cache::add($lockKey, (string) time(), self::LOCK_TTL_SECONDS)) {
            throw new DomainException(
                'IDEMPOTENCY_IN_PROGRESS',
                409,
                'IDEMPOTENCY_IN_PROGRESS'
            );
        }

        try {
            /** @var Response $response */
            $response = $next($request);

            if ($response instanceof JsonResponse && $response->isSuccessful()) {
                Cache::put($cacheKey, $this->snapshot($response), self::TTL_SECONDS);
            }

            return $response;
        } finally {
            Cache::forget($lockKey);
        }
    }

    private function cacheKey(Request $request, string $idempotencyKey, string $authorization): string
    {
        $identity = sha1($authorization);
        $signature = sha1(
            $request->getMethod()
            .'|'.$request->getRequestUri()
            .'|'.sha1((string) $request->getContent())
        );

        return 'rtmx:idem:'.$identity.':'.$idempotencyKey.':'.$signature;
    }

    /**
     * @return array{status: int, content_type: string|null, body: string}
     */
    private function snapshot(JsonResponse $response): array
    {
        return [
            'status' => $response->getStatusCode(),
            'content_type' => $response->headers->get('Content-Type'),
            'body' => (string) $response->getContent(),
        ];
    }

    /**
     * Valide la forme d'un snapshot mémorisé en cache (défense contre un
     * cache corrompu ou une clé collisionnée) avant de le rejouer.
     *
     * @phpstan-assert-if-true array{status: int, content_type: string|null, body: string} $value
     */
    private function isSnapshot(mixed $value): bool
    {
        return is_array($value)
            && array_key_exists('status', $value)
            && is_int($value['status'])
            && array_key_exists('content_type', $value)
            && ($value['content_type'] === null || is_string($value['content_type']))
            && array_key_exists('body', $value)
            && is_string($value['body']);
    }

    /**
     * @param  array{status: int, content_type: string|null, body: string}  $snapshot
     */
    private function replay(array $snapshot): Response
    {
        $response = new Response($snapshot['body'], $snapshot['status']);
        $response->headers->set('Content-Type', $snapshot['content_type'] ?? 'application/json');
        $response->headers->set('Idempotent-Replayed', 'true');

        return $response;
    }
}
