<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Services;

use App\Modules\Billing\Domain\Enums\PlanCode;
use App\Modules\Billing\Domain\Models\FeaturePlanMatrix;
use App\Modules\Billing\Domain\Models\Subscription;

/**
 * Garde d'entitlement (DEP-BC21 #6247).
 *
 * Sortie exigée par le backlog BC-21 : « un paiement ne débloque pas un
 * module hors entitlement ». Les capabilities d'une entreprise sont TOUJOURS
 * dérivées de l'intersection { plan de la souscription active } × { matrice
 * `feature_plan_matrix` } — les chemins de paiement/webhook ne mutent aucune
 * de ces deux sources (ils ne touchent ni la matrice, ni `companies.features`),
 * donc un paiement ne peut mécaniquement rien débloquer hors plan.
 *
 * Lecture FAIL-CLOSED :
 *   - pas de souscription active → plan `free` ;
 *   - feature absente de la matrice → désactivée ;
 *   - feature présente mais `enabled = false` → désactivée.
 */
class EntitlementGuard
{
    /**
     * Plan effectif d'une entreprise, dérivé de sa souscription active.
     */
    public function planForCompany(string $companyId): string
    {
        $subscription = Subscription::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->latest()
            ->first();

        return PlanCode::normalize($subscription->plan ?? PlanCode::Free->value)->value;
    }

    /**
     * Une feature est-elle activée pour l'entreprise (plan actif × matrice) ?
     */
    public function isFeatureEnabled(string $companyId, string $featureKey): bool
    {
        $entry = $this->matrixEntry($featureKey, $this->planForCompany($companyId));

        return $entry !== null && $entry->enabled;
    }

    /**
     * Limite d'usage de la feature pour l'entreprise (null = aucune limite).
     */
    public function featureLimit(string $companyId, string $featureKey): ?int
    {
        return $this->matrixEntry($featureKey, $this->planForCompany($companyId))?->limit_value;
    }

    /**
     * Intersection plan × matrice : feature_key → enabled.
     *
     * @return array<string, bool>
     */
    public function enabledFeaturesForPlan(string $plan): array
    {
        $plan = PlanCode::normalize($plan)->value;

        return FeaturePlanMatrix::query()
            ->where('plan', $plan)
            ->orderBy('feature_key')
            ->get()
            ->mapWithKeys(static fn (FeaturePlanMatrix $entry): array => [
                $entry->feature_key => (bool) $entry->enabled,
            ])
            ->all();
    }

    private function matrixEntry(string $featureKey, string $plan): ?FeaturePlanMatrix
    {
        return FeaturePlanMatrix::query()
            ->where('feature_key', $featureKey)
            ->where('plan', $plan)
            ->first();
    }
}
