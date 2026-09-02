<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * ATT-004 (#6769) — Idempotency-Key OBLIGATOIRE pour les écritures kiosque
 * versionnées (enrôlements : start/activate/revoke).
 *
 * Sémantique (miroir de `Core\Http\Middleware\IdempotencyMiddleware` #5277,
 * scopée appareil au lieu du header Authorization) :
 *   - clé absente/invalide → 422 (MISSING/INVALID_IDEMPOTENCY_KEY) ;
 *   - la première réponse 2xx est mémorisée 24 h et rejouée à l'identique
 *     pour toute retentative avec la même clé et le même corps
 *     (header `Idempotent-Replayed: true`) ;
 *   - le scope de cache est device_code (hash) + clé + méthode/URI/corps :
 *     aucune relecture inter-appareil, aucune fausse relecture pour un corps
 *     différent ;
 *   - les routes kiosque héritées (punch/sync/verify-face) restent
 *     rétro-compatibles : clé honorée quand présente, non exigée (BIO-007
 *     #6772, QLT-001 #6775) — le nouveau client kiosque (BIO-009 #6774)
 *     l'envoie systématiquement.
 */
final class RequireKioskIdempotencyKey
{
    private const TTL_SECONDS = 86400; // 24 h

    private const LOCK_TTL_SECONDS = 60;

    private const KEY_PATTERN = '/^[A-Za-z0-9._:-]{8,255}$/';

    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->headers->get('Idempotency-Key');

        if (! is_string($idempotencyKey) || $idempotencyKey === '') {
            abort(422, 'MISSING_IDEMPOTENCY_KEY');
        }

        if (! preg_match(self::KEY_PATTERN, $idempotencyKey)) {
            abort(422, 'INVALID_IDEMPOTENCY_KEY');
        }

        /** @var AttendanceKiosk|null $kiosk */
        $kiosk = $request->attributes->get('kiosk_device');
        if (! $kiosk instanceof AttendanceKiosk) {
            abort(401, 'INVALID_KIOSK_TOKEN');
        }

        $cacheKey = $this->cacheKey($request, $idempotencyKey, $kiosk);

        $cached = Cache::get($cacheKey);
        if ($this->isSnapshot($cached)) {
            return $this->replay($cached);
        }

        $lockKey = $cacheKey.':lock';
        if (! Cache::add($lockKey, (string) time(), self::LOCK_TTL_SECONDS)) {
            abort(409, 'IDEMPOTENCY_IN_PROGRESS');
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

    private function cacheKey(Request $request, string $idempotencyKey, AttendanceKiosk $kiosk): string
    {
        $deviceIdentity = md5((string) $kiosk->device_code);
        $signature = sha1(
            $request->getMethod()
            .'|'.$request->getRequestUri()
            .'|'.sha1((string) $request->getContent())
        );

        return 'kiosk:idem:'.$deviceIdentity.':'.$idempotencyKey.':'.$signature;
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
