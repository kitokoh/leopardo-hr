<?php

namespace App\Http\Middleware\AI;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AITenantInjector
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->company_id) {
            abort(403, 'AI requires a valid company context.');
        }

        $request->attributes->set('ai_company_id', $user->company_id);
        $request->attributes->set('ai_user_id', $user->id);

        return $next($request);
    }
}
