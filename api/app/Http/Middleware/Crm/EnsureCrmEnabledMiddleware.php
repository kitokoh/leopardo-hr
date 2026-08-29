<?php

declare(strict_types=1);

namespace App\Http\Middleware\Crm;

use App\Core\Feature\Infrastructure\Services\CrmFeature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Issue #5742 (CRM PRE) — gate `crm.enabled` pour les routes /api/v1/crm/*.
 *
 * Évaluation 100 % côté serveur (CrmFeature) : kill switch global, puis
 * commutateur global, puis flag tenant `crm` (opt-in plateforme, ADR-CRM-004).
 * Le frontend ne peut jamais s'auto-autoriser : désactivé → 403 standard
 * (error + message + localized_message), même code que la gate IA (#4697).
 */
class EnsureCrmEnabledMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Même résolution que la gate caméras : le middleware `tenant` a déjà
        // résolu la company courante (binding container) — ne PAS passer par
        // $user->company (search_path tenant, leçon #5582/#5584).
        $company = app()->bound('current_company') ? currentCompany() : null;

        if (! CrmFeature::enabled($company)) {
            $code = CrmFeature::killSwitchActive() ? 'CRM_KILL_SWITCH_ACTIVE' : 'CRM_FEATURE_DISABLED';

            return response()->json([
                'error' => $code,
                'message' => $code,
                'localized_message' => __('errors.'.$code),
            ], 403);
        }

        return $next($request);
    }
}
