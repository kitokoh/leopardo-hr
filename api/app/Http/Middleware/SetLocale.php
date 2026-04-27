<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        // 1. Authenticated user preference
        if ($user = $request->user()) {
            $userLocale = $user->preferred_language
                ?? $user->company?->language
                ?? null;

            if ($userLocale && Language::isSupported($userLocale)) {
                return strtolower($userLocale);
            }
        }

        // 2. Accept-Language header
        $header = $request->header('Accept-Language', '');
        $lang = strtolower(substr($header, 0, 2));

        if (Language::isSupported($lang)) {
            return $lang;
        }

        // 3. Fallback
        return Language::DEFAULT;
    }
}
