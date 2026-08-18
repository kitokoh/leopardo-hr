<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\CountryRulesResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaySlip
 */
class PaySlipResource extends JsonResource
{
    /**
     * Issue #2116 — bloc `compliance` (niveau de confiance des règles pays,
     * même contrat que `PayrollCalculationPresenter`) exposé sur les
     * bulletins. Le niveau dépend uniquement du pays du run : on résout une
     * seule fois par pays et par requête (les objets rules sont des configs
     * statiques, sans accès DB).
     *
     * @var array<string, array{level: string, warning: string, warning_key: string, source: string, verification_date: string|null}>
     */
    private static array $complianceCache = [];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_run_id' => $this->payroll_run_id,
            'employee_id' => $this->employee_id,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            // Issue #2116 — champs calculés pour les clients web
            // (rétro-compatibles : champs ADDITIFS, aucun retrait).
            // `period_start` est non-nullable (Carbon) : pas de nullsafe
            // (pattern PHPStan strict nullsafe.neverNull, baseline).
            'period' => $this->period_start->format('Y-m'),
            'employee_name' => $this->employee_name(),
            'country_code' => $this->payrollRun?->country_code,
            'compliance' => $this->compliancePayload(),
            'gross_salary' => $this->gross_salary,
            'total_deductions' => $this->total_deductions,
            'net_salary' => $this->net_salary,
            'currency' => currentCompany()?->currency ?? 'DZD',
            'employer_contributions' => $this->employer_contributions,
            'total_cost' => $this->total_cost,
            // #5018 (dossierdeConception §10.3) : décomposition du salaire selon
            // `salary_type` (fixed → mensuel ; daily → taux journalier ;
            // hourly → taux horaire) pour l'affichage UX du portail web.
            'salary_type' => $this->employee?->salary_type,
            'salary_base' => $this->employee?->salary_base !== null ? (float) $this->employee->salary_base : null,
            'hourly_rate' => $this->employee?->hourly_rate !== null ? (float) $this->employee->hourly_rate : null,
            'working_days' => $this->working_days,
            'actual_days_worked' => $this->actual_days_worked,
            'overtime_hours' => $this->overtime_hours,
            'status' => $this->status,
            'employee' => $this->whenLoaded('employee'),
            'lines' => $this->whenLoaded('lines'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function employee_name(): ?string
    {
        if ($this->employee === null) {
            return null;
        }

        return trim(($this->employee->first_name ?? '').' '.($this->employee->last_name ?? ''));
    }

    /**
     * Bloc `compliance` structuré (contrat #1872/#2116) — niveau de
     * confiance, avertissement localisé, source légale, date de vérification
     * experte. Null quand le pays du run est inconnu du moteur (rétro-
     * compatible : les clients ignorent le bloc, aucun affichage).
     *
     * @return array{level: string, warning: string, warning_key: string, source: string, verification_date: string|null}|null
     */
    private function compliancePayload(): ?array
    {
        $countryCode = $this->payrollRun?->country_code;
        if ($countryCode === null || isset(self::$complianceCache[$countryCode])) {
            return self::$complianceCache[$countryCode] ?? null;
        }

        $resolver = new CountryRulesResolver;
        if (! $resolver->supports($countryCode)) {
            return null;
        }

        $companyId = $this->company_id !== null ? (string) $this->company_id : null;
        $rules = $resolver->resolve($countryCode, $companyId);

        return self::$complianceCache[$countryCode] = [
            'level' => $rules->confidenceLevel(),
            'warning' => $rules->complianceWarning(),
            'warning_key' => 'payroll.compliance_warning_'.$rules->confidenceLevel(),
            'source' => $rules->complianceSource(),
            'verification_date' => $rules->verificationDate(),
        ];
    }
}
