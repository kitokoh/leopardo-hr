<?php

namespace App\Http\Middleware;

use App\Modules\Billing\Domain\Models\PartnerLink;
use App\Modules\Billing\Domain\Models\PartnerClick;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PartnerLinkMiddleware
{
    /**
     * #8060 — borne anti-DoS / anti-fraude à l'attribution :
     * 1 clic compté par (lien, IP) et par fenêtre de 15 min, plafond global
     * de 12 clics comptés / IP / heure (toutes campagnes confondues) et
     * filtre des user-agents de bots connus. La redirection et le cookie
     * d'attribution ne sont JAMAIS impactés — seule l'écriture du clic est
     * dédupliquée.
     */
    private const DEDUP_WINDOW_SECONDS = 900;

    private const MAX_CLICKS_PER_IP_PER_HOUR = 12;

    private const IP_WINDOW_SECONDS = 3600;

    private const BOT_UA_PATTERN = '/bot|crawler|spider|crawling|slurp|curl|wget|python-requests|httpclient|headless|phantom|selenium|lighthouse|pingdom|uptime|scrapy/i';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('p/*')) {
            // Check for cookie consent if policy requires it
            // For now, we assume consent is handled by the landing page or a common flag.
            // If consent is explicitly 'rejected', we don't drop the cookie.
            if ($request->cookie('leopardo_cookie_consent') === 'rejected') {
                return redirect('/signup');
            }

            $code = $request->segment(2);

            $link = PartnerLink::with('partner')
                ->where('code', $code)
                ->where('is_active', true)
                ->first();

            // Block if partner is suspended
            if ($link && $link->partner?->status !== 'active') {
                return redirect('/signup');
            }

            if ($link) {
                // Record click — borné (#8060) : sans dédup, un script trivial
                // gonflait partner_clicks (DoS stockage) ou simulait des clics
                // pour s'attribuer des conversions (fraude à l'attribution).
                if ($this->shouldCountClick($request, (int) $link->id)) {
                    // #4606 : user_agent/referrer_url viennent de headers clients —
                    // colonnes VARCHAR(255) : troncature obligatoire (un Referer
                    // long provoquait un QueryException → 500 sur route publique).
                    PartnerClick::create([
                        'partner_link_id' => $link->id,
                        'ip_address' => $request->ip(),
                        'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                        'referrer_url' => Str::limit((string) $request->header('referer'), 255, ''),
                    ]);
                }

                // Store cookie for 30 days
                return redirect('/signup')->withCookie(cookie(
                    'leopardo_referrer_id',
                    (string) $link->partner_id,
                    60 * 24 * 30,
                    '/',
                    null,
                    true, // secure
                    true  // httpOnly
                ));
            }

            return redirect('/signup');
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }

    /**
     * #8060 — décide si le hit doit être COMPTÉ (écriture DB). Jamais de
     * blocage fonctionnel : redirect + cookie restent identiques, seule
     * l'écriture est sautée pour un doublon / un bot / une IP en excès.
     *
     * Cache indisponible → fail-open documenté (même philosophie que
     * l'idempotence #6557 : on préfère compter plutôt que perdre toute
     * l'attribution pendant une panne Redis ; la borne principale reste la
     * dédup en fonctionnement normal).
     */
    private function shouldCountClick(Request $request, int $linkId): bool
    {
        $userAgent = (string) $request->userAgent();

        if ($userAgent !== '' && preg_match(self::BOT_UA_PATTERN, $userAgent) === 1) {
            return false;
        }

        try {
            // tenant-cache:shared — anti-fraude PLATEFORME par (lien, IP),
            // hors donnée tenant (#8058 ne scanne que app/Modules, marqueur
            // posé par cohérence).
            $dedupKey = 'partner_click:dedup:'.$linkId.':'.sha1((string) $request->ip());
            if (! Cache::add($dedupKey, 1, self::DEDUP_WINDOW_SECONDS)) {
                return false;
            }

            // tenant-cache:shared — plafond global par IP (toutes campagnes).
            $ipKey = 'partner_click:ip:'.sha1((string) $request->ip());
            Cache::add($ipKey, 0, self::IP_WINDOW_SECONDS);
            if ((int) Cache::increment($ipKey) > self::MAX_CLICKS_PER_IP_PER_HOUR) {
                return false;
            }

            return true;
        } catch (Throwable) {
            return true;
        }
    }
}

