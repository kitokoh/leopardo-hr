<?php

declare(strict_types=1);

namespace App\Http\Middleware\Cameras;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentifie la chaîne vidéo (MediaMTX / détecteur de mouvement) sur les
 * endpoints internes du module Caméras (issue #7427).
 *
 * Même secret partagé que `GET /internal/camera-token/verify`
 * (`config('cameras.mediamtx_secret')`, header `Authorization: Bearer …`),
 * mais factorisé en middleware pour que la route d'ingestion d'événements ne
 * duplique pas la vérification. Hors `local`/`testing`, un secret absent ou
 * invalide est refusé (fail-closed) : ces routes ne sont jamais ouvertes.
 *
 * Le contrat d'erreur reste celui de l'API (`error`, `message`,
 * `localized_message`).
 */
class EnsureMediamtxSecretMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isMediamtxAuthorized($request)) {
            return new JsonResponse([
                'error' => 'UNAUTHENTICATED',
                'message' => 'UNAUTHENTICATED',
                'localized_message' => __('errors.UNAUTHENTICATED'),
            ], 401);
        }

        return $next($request);
    }

    private function isMediamtxAuthorized(Request $request): bool
    {
        $expected = Config::get('cameras.mediamtx_secret');

        // En dev sans secret configuré, on autorise (local/testing) — même
        // comportement que InternalCameraTokenController.
        if (! is_string($expected) || $expected === '') {
            return in_array(app()->environment(), ['local', 'testing'], true);
        }

        $header = (string) $request->header('Authorization', '');

        if (stripos($header, 'Bearer ') === 0) {
            return hash_equals($expected, substr($header, 7));
        }

        return false;
    }
}
