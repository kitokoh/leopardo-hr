<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global security response headers.
 *
 * The Laravel API previously sent no security headers at all (no
 * X-Content-Type-Options, X-Frame-Options, Referrer-Policy, HSTS) — see
 * issue #1469. This middleware applies defence-in-depth defaults on every
 * response (API JSON and web/Blade alike):
 *
 * - X-Content-Type-Options: nosniff      — prevents MIME-sniffing of responses
 * - X-Frame-Options: SAMEORIGIN          — clickjacking protection; same-origin
 *                                          embedding (e.g. admin iframes of the
 *                                          platform) stays allowed
 * - Referrer-Policy: strict-origin-when-cross-origin — default modern policy
 * - Permissions-Policy: none             — disables camera/mic/geolocation/etc.
 * - Strict-Transport-Security            — only on HTTPS requests (never on
 *                                          plain HTTP local/dev)
 *
 * Kept deliberately minimal and non-breaking; CSP is handled at the
 * application layer where content is known (front/web next.config.ts).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        // #9352 (audit sécurité 2026-11-10, F10) — deux en-têtes cross-origin
        // manquaient :
        //  - `Cross-Origin-Resource-Policy: same-site` : empêche un site tiers
        //    d'embarquer une réponse de l'API (fuite de contenu par inclusion) ;
        //  - `Cross-Origin-Opener-Policy: same-origin` : isole le contexte de
        //    navigation, donc coupe l'accès à `window.opener` depuis une
        //    fenêtre tierce (Spectre / tabnabbing).
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-site');

        if (! $response->headers->has('Cross-Origin-Opener-Policy')) {
            $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        }

        // #9352 — CSP minimale sur les réponses NON-HTML de l'API. Elle
        // n'interdit rien d'utile (une réponse JSON n'a aucune ressource à
        // charger) mais ferme deux usages détournés : exécution de script si
        // un client force un rendu HTML, et cadrage (`frame-ancestors`). On ne
        // l'applique PAS aux réponses HTML/Blade, dont la CSP est gérée par la
        // couche applicative. Et comme pour `Referrer-Policy`, un endpoint qui
        // a déjà posé sa propre CSP (portail document, vitrine) est respecté.
        $contentType = (string) $response->headers->get('Content-Type', '');

        if (! str_contains($contentType, 'text/html')
            && ! $response->headers->has('Content-Security-Policy')
        ) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'"
            );
        }
        // Référence #5521 : un endpoint peut poser sa propre politique plus
        // stricte (ex. portail document partagé → no-referrer, l'URL porte un
        // token) — le défaut du middleware ne doit pas l'écraser.
        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
