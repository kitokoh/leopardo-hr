<?php

namespace App\Http\Middleware\AI;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Billing\Domain\Exceptions\InsufficientAiCreditsException;
use App\Modules\Billing\Domain\Services\EntitlementGuard;
use App\Modules\Billing\Infrastructure\Services\AiCreditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quota mensuel de requêtes IA par plan, puis débit des crédits achetés
 * (#7764, spec MISSION_ESPACE_CLIENT §3.4).
 *
 * Avant #7764 : plan codé en dur `'starter'` (un PlanCode qui n'existe plus)
 * et compteur mensuel en Cache VOLATIL (remis à zéro à chaque redéploiement).
 *
 * Désormais :
 *   1. le plan réel est dérivé via `EntitlementGuard` (fail-closed : pas de
 *      souscription active → `free`) ;
 *   2. le compteur mensuel est PERSISTANT (`ai_usage_counters`, une ligne par
 *      company × mois) — fallback Cache uniquement si la table n'existe pas
 *      (fixtures de test partielles, pattern WebhookEventRegistry #5265) ;
 *   3. quota du plan épuisé (`config('ai.quotas')`, null = illimité) → débit
 *      forfaitaire des crédits achetés (`AiCreditService::TOKENS_PER_REQUEST`
 *      sur le ledger) ;
 *   4. solde de crédits insuffisant → 422 `AI_CREDITS_EXHAUSTED`, FAIL-CLOSED
 *      (même contrat d'erreur que AI_QUOTA/AI_TOKEN_BUDGET : `{error, message,
 *      localized_message}`) — aucun appel LLM, aucun effet de bord.
 *
 * BOS-008 (#8143) : le quota du plan est consommé par une **décision
 * atomique** (`incrementMonthlyUsageIfUnderLimit` : `ON CONFLICT … WHERE
 * used < limit RETURNING used`) et non plus par un check-then-increment sur
 * une lecture périmée. Une requête refusée (quota atteint puis crédits à sec)
 * ne consomme jamais de compteur.
 */
class AIRateLimiter
{
    public function __construct(
        private readonly EntitlementGuard $entitlements,
        private readonly AiCreditService $aiCredits,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        // Contexte compagnie fail-closed : posé par AITenantInjector sur les
        // routes IA (`ai_company_id`), avec repli sur l'employé authentifié
        // (invocation directe du middleware, ex. tests).
        $companyId = strval($request->attributes->get('ai_company_id', ''));
        if ($companyId === '' && $user instanceof Employee) {
            $companyId = strval($user->company_id);
        }
        if ($companyId === '') {
            abort(403, strval(__('errors.AI_COMPANY_CONTEXT_REQUIRED')));
        }

        $period = now()->format('Y-m');

        $plan = $this->entitlements->planForCompany($companyId);

        /** @var array<string, int|null> $quotas */
        $quotas = config('ai.quotas', []);
        // Fail-closed : un plan absent du barème n'a AUCUN quota inclus
        // (il retombe sur les crédits achetés) ; `null` = illimité (enterprise).
        $limit = array_key_exists($plan, $quotas) ? $quotas[$plan] : 0;

        $used = $this->currentUsage($companyId, $period);

        // BOS-008 (#8143) : la décision est prise sur la valeur ATOMIQUE
        // post-incrément (`consumePlanQuota`), jamais sur la lecture `$used`
        // ci-dessus (qui ne sert plus qu'à l'observabilité). L'ancien
        // check-then-increment laissait passer N requêtes concurrentes lues
        // avant le premier incrément → dépassement du quota = coût provider.
        $grantedUsage = $this->consumePlanQuota($companyId, $period, $limit);

        if ($grantedUsage !== null) {
            return $next($request);
        }

        // Quota du plan épuisé → débit forfaitaire des crédits achetés.
        try {
            $this->aiCredits->debit($companyId, AiCreditService::TOKENS_PER_REQUEST);
        } catch (InsufficientAiCreditsException) {
            return response()->json([
                'error' => InsufficientAiCreditsException::ERROR_CODE,
                'message' => InsufficientAiCreditsException::ERROR_CODE,
                'localized_message' => __('errors.AI_CREDITS_EXHAUSTED', [], $request->getLocale()),
                'quota' => $limit,
                'used' => $used,
                'credits_balance' => $this->aiCredits->balance($companyId),
            ], 422);
        }

        $this->incrementUsage($companyId, $period);

        return $next($request);
    }

    /**
     * Consomme une unité de quota du plan SI elle est disponible — décision
     * atomique (BOS-008 #8143).
     *
     * @return int|null usage post-incrément, ou `null` si le quota du plan est
     *                  atteint (compteur NON modifié : un refus ne consomme rien)
     */
    private function consumePlanQuota(string $companyId, string $period, ?int $limit): ?int
    {
        // Plan illimité : aucune limite, on compte sans jamais refuser.
        if ($limit === null) {
            $this->incrementUsage($companyId, $period);

            return $this->currentUsage($companyId, $period);
        }

        if (schemaTableExists('ai_usage_counters')) {
            return $this->aiCredits->incrementMonthlyUsageIfUnderLimit($companyId, $period, $limit);
        }

        // Fallback volatil (fixtures de test partielles uniquement) :
        // best-effort, non atomique — jamais en production (migration tenant).
        $used = (int) Cache::get($this->cacheKey($companyId, $period), 0);

        if ($used >= $limit) {
            return null;
        }

        $this->incrementUsage($companyId, $period);

        return $used + 1;
    }

    private function currentUsage(string $companyId, string $period): int
    {
        if (schemaTableExists('ai_usage_counters')) {
            return $this->aiCredits->monthlyUsage($companyId, $period);
        }

        return (int) Cache::get($this->cacheKey($companyId, $period), 0);
    }

    private function incrementUsage(string $companyId, string $period): void
    {
        if (schemaTableExists('ai_usage_counters')) {
            $this->aiCredits->incrementMonthlyUsage($companyId, $period);

            return;
        }

        // Fallback volatil (fixtures de test partielles uniquement — en
        // production la migration tenant existe).
        $key = $this->cacheKey($companyId, $period);
        Cache::put($key, (int) Cache::get($key, 0) + 1, now()->endOfMonth());
    }

    private function cacheKey(string $companyId, string $period): string
    {
        return "ai_quota:{$companyId}:{$period}";
    }
}
