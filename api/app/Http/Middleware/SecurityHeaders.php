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
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
