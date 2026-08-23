<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Infrastructure\Services\CountryRulesResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Récapitulatif du préavis légal par pays/ancienneté (issue #5325, gap G2).
 *
 * LECTURE SEULE : la règle Payroll (CountryRulesInterface::noticePeriodDays)
 * est la source de vérité — ce service AFFICHE, il ne recalcule jamais.
 * L'ancienneté est calculée avec la MÊME définition que le moteur Payroll
 * (EndOfContractService::yearsOfService : mois entiers depuis
 * contract_start, divisés par 12) pour que le récapitulatif reste cohérent
 * avec le solde de tout compte (golden test de référence).
 */
class DepartureNoticeService
{
    public function __construct(
        private readonly CountryRulesResolver $rulesResolver,
    ) {}

    /**
     * @return array{
     *     country_code: string,
     *     years_of_service: float,
     *     notice_days: float,
     *     notice_status: string,
     *     notice_days_served: int|null,
     *     rule_reference: string,
     *     confidence_level: string,
     *     compliance_source: string|null,
     *     rules_version: string,
     * }
     */
    public function summaryFor(Employee $employee, ?Carbon $asOf = null): array
    {
        $endDate = $asOf ?? Carbon::now();
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = currentCompany();
        $country = strtoupper((string) $company->country);

        // Pays non enregistré → UnsupportedCountryRulesException (422) —
        // aucun fallback silencieux (#1868).
        $rules = $this->rulesResolver->resolve($country, $company->id);

        $yearsOfService = $this->yearsOfService($employee, $endDate);
        $noticeDays = $rules->noticePeriodDays($yearsOfService);

        $departure = $this->latestDeparture($employee);
        $noticeStatus = 'unknown';
        $noticeDaysServed = null;
        if ($departure !== null) {
            $noticeStatus = $departure['notice_served'] ? 'served' : 'not_served';
            $noticeDaysServed = $departure['notice_days_served'];
        }

        return [
            'country_code' => $country,
            'years_of_service' => round($yearsOfService, 2),
            'notice_days' => round($noticeDays, 0),
            'notice_status' => $noticeStatus,
            'notice_days_served' => $noticeDaysServed,
            'rule_reference' => (new \ReflectionClass($rules))->getShortName().'::noticePeriodDays()',
            'confidence_level' => $rules->confidenceLevel(),
            'compliance_source' => $rules->complianceSource(),
            'rules_version' => $rules->rulesVersion(),
        ];
    }

    /**
     * Même définition d'ancienneté que le moteur Payroll
     * (EndOfContractService::yearsOfService) : mois entiers depuis
     * contract_start, divisés par 12.
     */
    private function yearsOfService(Employee $employee, Carbon $endDate): float
    {
        if ($employee->contract_start === null) {
            return 0.0;
        }

        return $employee->contract_start->diffInMonths($endDate) / 12.0;
    }

    /**
     * Dernier enregistrement de départ (workflow #5324) — lecture RÉSILIENTE :
     * la table `employee_departures` n'existe pas encore sur tous les
     * environnements (migration portée par la branche #5324) → fail-open avec
     * statut `unknown` (pattern repo, tenants historiques).
     *
     * @return array{notice_served: bool, notice_days_served: int|null}|null
     */
    private function latestDeparture(Employee $employee): ?array
    {
        if (! Schema::hasTable('employee_departures')) {
            return null;
        }

        $row = DB::table('employee_departures')
            ->where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->first(['notice_served', 'notice_days_served']);

        if ($row === null) {
            return null;
        }

        return [
            'notice_served' => (bool) $row->notice_served,
            'notice_days_served' => $row->notice_days_served !== null ? (int) $row->notice_days_served : null,
        ];
    }
}
