<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Payroll\Domain\Exceptions\UnsupportedCountryRulesException;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaySlip
 */
class PaySlipResource extends JsonResource
{
    /**
     * Cache du bloc conformité par pays (contrat #1872) — résolu une seule
     * fois par pays par requête (les bulletins d'une page partagent le même
     * pays de run dans l'immense majorité des cas).
     *
     * @var array<string, array{level: string, warning: string, warning_key: string, source: string|null, verification_date: string|null}>
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
            'gross_salary' => $this->gross_salary,
            'total_deductions' => $this->total_deductions,
            'net_salary' => $this->net_salary,
            'currency' => currentCompany()?->currency ?? 'DZD',
            'employer_contributions' => $this->employer_contributions,
            'total_cost' => $this->total_cost,
            'working_days' => $this->working_days,
            'actual_days_worked' => $this->actual_days_worked,
            'overtime_hours' => $this->overtime_hours,
            'status' => $this->status,
            // Issue #2116 — bloc conformité par niveau de confiance (contrat
            // #1872) exposé sur les bulletins : level, warning, source légale
            // et date de vérification experte (nullable). Résolu depuis le
            // pays du payroll run (ou pays du tenant en repli).
            'compliance' => $this->resolveCompliance($this->payrollRun?->country_code),
            'employee' => $this->whenLoaded('employee'),
            'lines' => $this->whenLoaded('lines'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Résout (et mémoïse) le bloc conformité pour un pays — même vocabulaire
     * que PayrollCalculationPresenter : niveau de confiance, avertissement,
     * clé localisée, source légale et date de vérification experte.
     *
     * @return array{level: string, warning: string, warning_key: string, source: string|null, verification_date: string|null}
     */
    protected function resolveCompliance(?string $countryCode): array
    {
        $country = $countryCode ?? currentCompany()?->country ?? 'DZ';

        if (isset(self::$complianceCache[$country])) {
            return self::$complianceCache[$country];
        }

        try {
            $rules = app(PayrollCalculator::class)->getRules($country);

            return self::$complianceCache[$country] = [
                'level' => $rules->confidenceLevel(),
                'warning' => $rules->complianceWarning(),
                'warning_key' => 'payroll.compliance_warning_'.$rules->confidenceLevel(),
                'source' => $rules->complianceSource(),
                'verification_date' => $rules->verificationDate(),
            ];
        } catch (UnsupportedCountryRulesException) {
            return self::$complianceCache[$country] = [
                'level' => 'unknown',
                'warning' => __('payroll.compliance_warning_unknown'),
                'warning_key' => 'payroll.compliance_warning_unknown',
                'source' => null,
                'verification_date' => null,
            ];
        }
    }
}
