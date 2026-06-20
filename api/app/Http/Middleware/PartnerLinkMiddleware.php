<?php

namespace App\Http\Middleware;

use App\Models\PartnerLink;
use App\Models\PartnerClick;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PartnerLinkMiddleware
{
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
            if ($link && $link->partner->status !== 'active') {
                return redirect('/signup');
            }

            if ($link) {
                // Record click
                PartnerClick::create([
                    'partner_link_id' => $link->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'referrer_url' => $request->header('referer'),
                ]);

                // Store cookie for 30 days
                return redirect('/signup')->withCookie(cookie(
                    'leopardo_referrer_id',
                    $link->partner_id,
                    60 * 24 * 30,
                    '/',
                    null,
                    true, // secure
                    true  // httpOnly
                ));
            }

            return redirect('/signup');
        }

        return $next($request);
    }
}
