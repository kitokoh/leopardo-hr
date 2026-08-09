<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunLockedException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Domain\Models\SalaryComponent;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AbstractCountryRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CanadaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\FrancePayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\MoroccoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\TunisiaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\TurkeyPayrollRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PayrollCalculator
{
    /** Jours ouvrés standards mensuels (DZ) — docs/payroll/DZ_COMPLIANCE.md §5. */
    public const STANDARD_WORKING_DAYS = 22;

    /** Heures mensuelles de référence (base / 173,33 h). */
    public const MONTHLY_HOURS = 173.33;

    /** @var array<string, CountryRulesInterface> */
    private array $rulesMap;

    /**
     * @param  iterable<CountryRulesInterface>  $countryRules
     */
    public function __construct(iterable $countryRules = [])
    {
        $this->rulesMap = [];

        foreach ($countryRules as $rule) {
            $this->rulesMap[$rule->countryCode()] = $rule;
        }

        if ($this->rulesMap === []) {
            $this->rulesMap = [
                'DZ' => new AlgeriaPayrollRules,
                'MA' => new MoroccoPayrollRules,
                'TN' => new TunisiaPayrollRules,
                'FR' => new FrancePayrollRules,
                'TR' => new TurkeyPayrollRules,
                'SN' => new SenegalPayrollRules,
            ];

            // CEMAC zone (PA2-COUNTRY-007): one CemacPayrollRules instance per
            // member state, each scoped via forMemberCountry() so countryCode()
            // returns the actual ISO 3166-1 alpha-2 code (CemacPayrollRules is
            // a single class covering all six members, not six separate ones).
            foreach (CemacPayrollRules::MEMBER_COUNTRY_CODES as $memberCountryCode) {
                $this->rulesMap[$memberCountryCode] = (new CemacPayrollRules)->forMemberCountry($memberCountryCode);
            }

            // CEDEAO/UEMOA zone (PA2-COUNTRY-008): same pattern as CEMAC above,
            // one CedeaoPayrollRules instance per XOF member state (Senegal
            // already has its own dedicated SenegalPayrollRules and is not
            // duplicated here).
            foreach (CedeaoPayrollRules::MEMBER_COUNTRY_CODES as $memberCountryCode) {
                $this->rulesMap[$memberCountryCode] = (new CedeaoPayrollRules)->forMemberCountry($memberCountryCode);
            }

            // Canada (PA2-COUNTRY-009): single ISO country code CA, province
            // is an optional refinement (timezone/overtime threshold) rather
            // than a separate registered country code — see CanadaPayrollRules
            // docblock. Federal defaults (no province) are registered here;
            // callers with a known province should use forProvince().
            $this->rulesMap['CA'] = new CanadaPayrollRules;
        }
    }

    public function getRules(string $countryCode): CountryRulesInterface
    {
        if (! isset($this->rulesMap[$countryCode])) {
            throw new \InvalidArgumentException("No payroll rules for country: {$countryCode}");
        }

        return $this->rulesMap[$countryCode];
    }

    public function calculateRun(PayrollRun $run): PayrollRun
    {
        // Programme FOCUS (F-11) : un run verrouillé (clôture comptable) ne peut
        // plus être recalculé — aucune modification silencieuse après clôture.
        if ($run->status === PayrollRun::STATUS_LOCKED) {
            throw new PayrollRunLockedException('Payroll run is locked (closing done). Unlock with reason first.');
        }

        $companyId = $run->company_id;
        $rules = $this->getRules($run->country_code);
        // Scope the rules to this company so any company-specific TaxSlab/
        // SocialContribution overrides configured via TaxSlabController/
        // SocialContributionController are actually applied (see
        // AbstractCountryRules::forCompany()). Falls back to global
        // (company_id IS NULL) rows, then to the hardcoded defaults.
        //
        // Also scope to the run's own period_start (PA2-ARCH-004): country
        // tax slabs/social contributions are associated with an effective
        // date, so recalculating a past run (e.g. for an audit) resolves
        // the rates that were effective *during that run's own period*,
        // not today's rates. This makes calculateRun() safe to call again
        // on an old run without silently drifting its figures forward to
        // whatever rates happen to be current today.
        if ($rules instanceof AbstractCountryRules) {
            $rules = $rules->forCompany($companyId)->asOf($run->period_start);
        }

        /** @var Collection<int, Employee> $employees */
        $employees = Employee::where('company_id', $companyId)
            ->where('status', 'active')
            ->get();

        /** @var Collection<int, SalaryStructure> $structuresCollection */
        $structuresCollection = SalaryStructure::where('company_id', $companyId)
            ->where('country_code', $run->country_code)
            ->where('active', true)
            ->with('components')
            ->get();

        /** @var Collection<int|string, SalaryStructure> $structures */
        $structures = $structuresCollection->keyBy('id');

        /** @var SalaryStructure|null $defaultStructure */
        $defaultStructure = $structures->first();

        DB::transaction(function () use ($run, $employees, $defaultStructure, $rules) {
            $run->paySlips()->delete();

            $totalGross = 0.0;
            $totalDeductions = 0.0;
            $totalNet = 0.0;
            $totalEmployerCost = 0.0;

            foreach ($employees as $employee) {
                /** @var SalaryStructure|null $structure */
                $structure = $defaultStructure;

                if (! $structure) {
                    continue;
                }

                $slip = $this->calculateSlip($run, $employee, $structure, $rules);

                $totalGross += (float) $slip->gross_salary;
                $totalDeductions += (float) $slip->total_deductions;
                $totalNet += (float) $slip->net_salary;
                $totalEmployerCost += (float) $slip->total_cost;
            }

            $run->update([
                'status' => 'calculated',
                'total_gross' => round($totalGross, 2),
                'total_deductions' => round($totalDeductions, 2),
                'total_net' => round($totalNet, 2),
                'total_employer_cost' => round($totalEmployerCost, 2),
                'employee_count' => $run->paySlips()->count(),
                'calculated_at' => now(),
            ]);
        });

        return $run->refresh();
    }

    private function calculateSlip(
        PayrollRun $run,
        Employee $employee,
        SalaryStructure $structure,
        CountryRulesInterface $rules
    ): PaySlip {
        $baseSalary = $structure->base_salary;
        $worked = $this->computeWorkedDays($run, $employee);
        $inputs = $this->collectWorkInputs($run, $employee);

        // Jours d'absence (payés ou non) retirés des jours travaillés ;
        // les congés payés sont compensés par l'indemnité (F-07).
        $leaveDays = $inputs['paid_leave_days'] + $inputs['unpaid_leave_days'];
        $worked['actual_days_worked'] = max(0.0, $worked['actual_days_worked'] - $leaveDays);
        $worked['overtime_hours'] = $inputs['overtime_hours'];

        $basePaid = $this->computeProratedBase($baseSalary, $worked['working_days'], $worked['actual_days_worked']);
        $overtimePay = $this->computeOvertimePay($baseSalary, $worked['overtime_hours']);
        $leaveIndemnity = $inputs['paid_leave_days'] > 0.0
            ? $this->computeLeaveIndemnity(
                $baseSalary,
                $inputs['paid_leave_days'],
                $worked['working_days'],
                $baseSalary * 12.0 // référence 12 mois (approximation documentée F-07)
            )
            : 0.0;

        $grossEarnings = $basePaid;
        $lines = [];
        $order = 0;

        $lines[] = [
            'name' => 'Salaire de base',
            'type' => 'earning',
            'base_amount' => $basePaid,
            'rate' => null,
            'amount' => $basePaid,
            'order' => $order++,
        ];

        if ($overtimePay > 0.0) {
            $lines[] = [
                'name' => 'Heures supplémentaires',
                'type' => 'earning',
                'base_amount' => (float) $worked['overtime_hours'],
                'rate' => null,
                'amount' => $overtimePay,
                'order' => $order++,
            ];
            $grossEarnings += $overtimePay;
        }

        if ($leaveIndemnity > 0.0) {
            $lines[] = [
                'name' => 'Indemnité de congés payés',
                'type' => 'earning',
                'base_amount' => $inputs['paid_leave_days'],
                'rate' => null,
                'amount' => $leaveIndemnity,
                'order' => $order++,
            ];
            $grossEarnings += $leaveIndemnity;
        }

        /** @var Collection<int, SalaryComponent> $components */
        $components = $structure->components->where('active', true)->sortBy('order');
        foreach ($components as $component) {
            /** @var SalaryComponent $component */
            if ($component->type !== 'earning') {
                continue;
            }
            $amount = $this->computeComponentAmount($component, $baseSalary, $grossEarnings);
            $grossEarnings += $amount;
            $lines[] = [
                'salary_component_id' => $component->id,
                'name' => $component->name,
                'type' => 'earning',
                'base_amount' => $baseSalary,
                'rate' => $component->percentage,
                'amount' => $amount,
                'order' => $order++,
            ];
        }

        /** @var array{employee: float, employer: float} $social */
        $social = $rules->calculateSocialCharges($grossEarnings);

        $lines[] = [
            'name' => 'Cotisations salariales',
            'type' => 'deduction',
            'base_amount' => $grossEarnings,
            'rate' => null,
            'amount' => $social['employee'],
            'order' => $order++,
        ];

        $taxableGross = $grossEarnings - $social['employee'];
        $incomeTax = $rules->calculateIncomeTax($taxableGross);

        $lines[] = [
            'name' => 'Impot sur le revenu',
            'type' => 'deduction',
            'base_amount' => $taxableGross,
            'rate' => null,
            'amount' => $incomeTax,
            'order' => $order++,
        ];

        foreach ($components as $component) {
            /** @var SalaryComponent $component */
            if ($component->type !== 'deduction') {
                continue;
            }
            $amount = $this->computeComponentAmount($component, $baseSalary, $grossEarnings);
            $lines[] = [
                'salary_component_id' => $component->id,
                'name' => $component->name,
                'type' => 'deduction',
                'base_amount' => $grossEarnings,
                'rate' => $component->percentage,
                'amount' => $amount,
                'order' => $order++,
            ];
        }

        $lines[] = [
            'name' => 'Cotisations patronales',
            'type' => 'employer_contribution',
            'base_amount' => $grossEarnings,
            'rate' => null,
            'amount' => $social['employer'],
            'order' => $order++,
        ];

        $totalDeductions = $social['employee'] + $incomeTax;
        foreach ($lines as $line) {
            if ($line['type'] === 'deduction' && $line['name'] !== 'Cotisations salariales' && $line['name'] !== 'Impot sur le revenu') {
                $totalDeductions += $line['amount'];
            }
        }

        $netSalary = round(max(0, $grossEarnings - $totalDeductions), 2);
        $totalCost = round($grossEarnings + $social['employer'], 2);

        /** @var PaySlip $slip */
        $slip = PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => round($grossEarnings, 2),
            'total_deductions' => round($totalDeductions, 2),
            'net_salary' => $netSalary,
            'employer_contributions' => round($social['employer'], 2),
            'total_cost' => $totalCost,
            'working_days' => $worked['working_days'],
            'actual_days_worked' => $worked['actual_days_worked'],
            'overtime_hours' => $worked['overtime_hours'],
            'status' => 'calculated',
        ]);

        foreach ($lines as $line) {
            PaySlipLine::create(array_merge($line, ['pay_slip_id' => $slip->id]));
        }

        return $slip;
    }

    /**
     * Programme FOCUS (F-05) — prorata du salaire de base.
     *
     * Règle : base × (jours effectivement travaillés / jours ouvrés standards).
     * Entrée/sortie en cours de mois, absences et congés sans solde passent
     * tous par ce mécanisme (actual_days_worked < working_days).
     */
    public function computeProratedBase(float $baseSalary, float $workingDays, float $actualDays): float
    {
        if ($workingDays <= 0.0 || $actualDays >= $workingDays) {
            return round($baseSalary, 2);
        }

        if ($actualDays <= 0.0) {
            return 0.0;
        }

        return round($baseSalary * ($actualDays / $workingDays), 2);
    }

    /**
     * Programme FOCUS (F-05) — paiement des heures supplémentaires.
     *
     * Taux horaire = base / 173,33 h (mensuel légal de référence).
     * Majorations : 25 % jusqu'à $standardRateHours h/mois, 50 % au-delà
     * (seuil conventionnel paramétrable — à confirmer par la convention
     * collective applicable, voir docs/payroll/DZ_COMPLIANCE.md §5).
     */
    public function computeOvertimePay(float $baseSalary, float $overtimeHours, int $standardRateHours = 10): float
    {
        if ($overtimeHours <= 0.0 || $baseSalary <= 0.0) {
            return 0.0;
        }

        $hourlyRate = round($baseSalary / self::MONTHLY_HOURS, 2);
        $standard = min($overtimeHours, (float) $standardRateHours);
        $premium = max(0.0, $overtimeHours - (float) $standardRateHours);

        return round(($standard * $hourlyRate * 1.25) + ($premium * $hourlyRate * 1.50), 2);
    }

    /**
     * Programme FOCUS (F-05) — jours travaillés sur la période du run.
     *
     * Recoupe le contrat de l'employé (contract_start / contract_end) avec la
     * période du run : prorata d'entrée/sortie en cours de mois.
     *
     * overtime_hours : source future = pointage/attendance (F-20) ; 0 tant que
     * le lien présence → paie n'est pas branché.
     *
     * @return array{working_days: float, actual_days_worked: float, overtime_hours: float}
     */
    public function computeWorkedDays(PayrollRun $run, Employee $employee): array
    {
        $workingDays = (float) self::STANDARD_WORKING_DAYS;
        $periodStart = $run->period_start->copy()->startOfDay();
        $periodEnd = $run->period_end->copy()->startOfDay();

        $overlapStart = $periodStart->copy();
        if ($employee->contract_start !== null && $employee->contract_start->gt($periodStart)) {
            $overlapStart = $employee->contract_start->copy()->startOfDay();
        }

        $overlapEnd = $periodEnd->copy();
        if ($employee->contract_end !== null && $employee->contract_end->lt($periodEnd)) {
            $overlapEnd = $employee->contract_end->copy()->startOfDay();
        }

        $periodDays = $periodStart->diffInDays($periodEnd) + 1;
        $overlapDays = max(0, $overlapStart->diffInDays($overlapEnd) + 1);

        $ratio = $periodDays > 0 ? min(1.0, $overlapDays / $periodDays) : 0.0;
        $actualDays = round($workingDays * $ratio, 2);

        return [
            'working_days' => $workingDays,
            'actual_days_worked' => $actualDays,
            'overtime_hours' => 0.0,
        ];
    }

    /**
     * Programme FOCUS (F-07) — indemnité de congés payés.
     *
     * Règle (docs/payroll/DZ_COMPLIANCE.md §4) : la PLUS FAVORABLE entre
     *  - maintien de salaire : base mensuelle × jours de congé / jours ouvrés,
     *  - règle du 1/10ᵉ : (salaires bruts des 12 mois de référence / 10)
     *    × (jours pris / congés acquis sur la période).
     *
     * Intégration à venir : alimentée par les absences approuvées (F-20),
     * versée dans le bulletin lors d'un départ en congé.
     */
    public function computeLeaveIndemnity(
        float $monthlyBase,
        float $leaveDays,
        float $workingDays,
        float $referenceGross12Months,
        float $accruedDaysTotal = 30.0
    ): float {
        if ($leaveDays <= 0.0) {
            return 0.0;
        }

        $maintien = $workingDays > 0.0 ? $monthlyBase * ($leaveDays / $workingDays) : 0.0;
        $dixieme = $accruedDaysTotal > 0.0
            ? ($referenceGross12Months / 10.0) * ($leaveDays / $accruedDaysTotal)
            : 0.0;

        return round(max($maintien, $dixieme), 2);
    }

    /**
     * Programme FOCUS (F-20) — entrées de travail réelles d'un employé sur la
     * période du run : heures sup (pointage) + jours de congés approuvés.
     *
     * Sources :
     *  - AttendanceLog.overtime_hours (somme des logs non annulés/rejetés) ;
     *  - Absence approuvées (status=approved), ventilées payées (is_paid) /
     *    non payées via AbsenceType.
     *
     * @return array{overtime_hours: float, paid_leave_days: float, unpaid_leave_days: float}
     */
    public function collectWorkInputs(PayrollRun $run, Employee $employee): array
    {
        $overtimeHours = AttendanceLog::query()
            ->where('company_id', $run->company_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$run->period_start, $run->period_end])
            ->where('overtime_hours', '>', 0)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->sum('overtime_hours');

        $paidLeave = $this->sumApprovedLeaveDays($run, $employee, true);
        $unpaidLeave = $this->sumApprovedLeaveDays($run, $employee, false);

        return [
            'overtime_hours' => (float) $overtimeHours,
            'paid_leave_days' => $paidLeave,
            'unpaid_leave_days' => $unpaidLeave,
        ];
    }

    private function sumApprovedLeaveDays(PayrollRun $run, Employee $employee, bool $paid): float
    {
        return (float) Absence::query()
            ->where('company_id', $run->company_id)
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $run->period_end)
            ->whereDate('end_date', '>=', $run->period_start)
            ->whereHas('absenceType', function (Builder $q) use ($paid): void {
                $q->where('is_paid', $paid);
            })
            ->sum('days_count');
    }

    /**
     * Programme FOCUS (F-08) — solde de tout compte (fin de contrat).
     *
     * Composants :
     *  - prorata du mois de départ (jours travaillés / jours ouvrés),
     *  - indemnité de congés non pris (règle F-07 : maintien vs 1/10ᵉ),
     *  - indemnité de préavis non effectué (jours × base/jours ouvrés),
     *  - indemnité de licenciement : base × années d'ancienneté ×
     *    $severanceMonthsPerYear (par défaut 1 mois/an — À CONFIRMER,
     *    loi 90-11, voir docs/payroll/DZ_COMPLIANCE.md §4).
     *
     * @return array{prorated_pay: float, leave_indemnity: float, notice_pay: float, severance: float, total: float}
     */
    public function computeFinalSettlement(
        float $monthlyBase,
        float $yearsOfService,
        float $proratedDays,
        float $workingDays,
        float $unpaidLeaveDays,
        float $referenceGross12Months,
        float $severanceMonthsPerYear = 1.0,
        float $noticeDays = 0.0
    ): array {
        $proratedPay = $this->computeProratedBase($monthlyBase, $workingDays, $proratedDays);
        $leaveIndemnity = $this->computeLeaveIndemnity($monthlyBase, $unpaidLeaveDays, $workingDays, $referenceGross12Months);
        $noticePay = $noticeDays > 0.0
            ? $this->computeProratedBase($monthlyBase, $workingDays, $noticeDays)
            : 0.0;
        $severance = $yearsOfService > 0.0
            ? round($monthlyBase * $yearsOfService * $severanceMonthsPerYear, 2)
            : 0.0;

        $total = round($proratedPay + $leaveIndemnity + $noticePay + $severance, 2);

        return [
            'prorated_pay' => $proratedPay,
            'leave_indemnity' => $leaveIndemnity,
            'notice_pay' => $noticePay,
            'severance' => $severance,
            'total' => $total,
        ];
    }

    private function computeComponentAmount(SalaryComponent $component, float $baseSalary, float $grossSalary): float
    {
        return match ($component->calculation_type) {
            'fixed' => round((float) $component->amount, 2),
            'percentage_of_base' => round($baseSalary * ((float) $component->percentage / 100), 2),
            'percentage_of_gross' => round($grossSalary * ((float) $component->percentage / 100), 2),
            default => 0.0,
        };
    }
}
