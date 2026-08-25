<?php

declare(strict_types=1);

namespace App\Modules\Planning\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Contracts\LegalLeaveCountryRuleInterface;
use App\Modules\Planning\Domain\Exceptions\UnsupportedLeaveCountryException;
use App\Modules\Planning\Domain\Models\LeavePolicy;
use App\Modules\Planning\Infrastructure\Services\CountryRules\LegalLeaveRulesRegistry;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Issue #5289 — résolution des règles légales de congés pour une entreprise.
 *
 * Façade applicative au-dessus du registre `LegalLeaveRulesRegistry` :
 *   1. `resolve()` : règle légale du pays d'une entreprise (exception typée
 *      si pays non supporté — aucun fallback silencieux) ;
 *   2. `monthlyFloorForPolicy()` : plancher légal mensuel applicable à une
 *      politique de congés (null = aucun plancher : pays non supporté OU
 *      politique non concernée) — utilisé par `leave:accrue` pour garantir
 *      que l'acquisition n'est jamais inférieure au minimum légal.
 */
final class LegalLeaveRulesService
{
    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Règle légale de congés du pays de l'entreprise.
     *
     * @throws UnsupportedLeaveCountryException
     */
    public function resolve(Company $company): LegalLeaveCountryRuleInterface
    {
        return LegalLeaveRulesRegistry::resolve((string) $company->country);
    }

    /**
     * Règle légale de congés pour un code pays (méthode pure, sans entreprise).
     *
     * @throws UnsupportedLeaveCountryException
     */
    public function resolveForCountry(string $countryCode): LegalLeaveCountryRuleInterface
    {
        return LegalLeaveRulesRegistry::resolve($countryCode);
    }

    /**
     * Plancher légal mensuel (jours) applicable à une politique de congés.
     *
     * Règles d'application (spec #5289 FR-003) :
     *  - pays de l'entreprise supporté par le registre, sinon null ;
     *  - politique `accrual_type = monthly`, sinon null ;
     *  - absence type associée `deducts_leave = true` (congés déductibles),
     *    sinon null — les absences non déductibles (maladie…) n'ont pas de
     *    plancher légal de congés annuels ;
     *  - retourne l'acquisition mensuelle légale (ex. 2,5 j pour la DZ).
     */
    public function monthlyFloorForPolicy(LeavePolicy $policy, Company $company): ?float
    {
        if ($policy->accrual_type !== 'monthly') {
            return null;
        }

        $countryCode = strtoupper(trim((string) $company->country));
        if ($countryCode === '' || ! LegalLeaveRulesRegistry::has($countryCode)) {
            return null; // pays non couvert : comportement historique préservé
        }

        $absenceType = $policy->absenceType;
        if ($absenceType === null || ! $absenceType->deducts_leave) {
            return null;
        }

        $cacheKey = sprintf('legal-leave-monthly-floor:%s', $countryCode);

        return (float) $this->cache->remember($cacheKey, 86_400, fn (): float => $this->resolveForCountry($countryCode)->accrualDaysPerMonth());
    }
}
