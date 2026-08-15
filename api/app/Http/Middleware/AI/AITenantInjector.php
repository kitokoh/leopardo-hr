<?php

namespace App\Http\Middleware\AI;

use Closure;
use App\Core\Tenant\Domain\Models\Company;
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

        // Issue #2690 (QA 2026-08-15) — lier `current_company` comme le fait le
        // middleware tenant : sans ce binding, les scopes BelongsToCompany des
        // modèles (qui se reposent sur currentCompany()) tombent en fallback
        // et un oubli de scope manuel = fuite cross-tenant.
        if (! app()->bound('current_company')) {
            $company = Company::query()->find($user->company_id);

            if ($company !== null) {
                app()->instance('current_company', $company);
            }
        }

        return $next($request);
    }
}
