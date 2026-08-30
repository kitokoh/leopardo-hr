<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface as CountryRulesContract;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunLockedException;
use App\Modules\Payroll\Domain\Exceptions\UnsupportedCountryRulesException;
use App\Modules\Payroll\Domain\Models\PayrollCalculationAudit;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AbstractCountryRules;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayrollCalculator
{
    /** Jours ouvrés standards mensuels (DZ) — docs/payroll/DZ_COMPLIANCE.md §5. */
    public const STANDARD_WORKING_DAYS = 22;

    /** Heures mensuelles de référence (base / 173,33 h). */
    public const MONTHLY_HOURS = PaySlipValueCalculator::MONTHLY_HOURS;

    private CountryRulesResolver $resolver;

    private PayrollCalculationAuditRecorder $auditRecorder;

    /** #5591 — agrégats des entrées de travail (service extrait du god-object). */
    private PayrollWorkInputAggregator $workInputAggregator;

    /** #5591 (slice 2) — calcul des VALEURS de bulletin (service extrait du god-object). */
    private PaySlipValueCalculator $slipValues;

    /** #5591 (slice 3) — calcul d'un run de régularisation (service extrait du god-object). */
    private PayrollRegularizationCalculator $regularization;

    /**
     * @param  iterable<CountryRulesInterface>  $countryRules  règles custom (tests) ; vide → résolveur par défaut
     */
    public function __construct(
        iterable $countryRules = [],
        private readonly ?PublicHolidayService $publicHolidayService = null,
        ?PayrollCalculationAuditRecorder $auditRecorder = null,
        ?PayrollWorkInputAggregator $workInputAggregator = null,
    ) {
        // MULTI-PAYS (#1868) : point d'entrée unique pour la résolution des
        // règles pays — la map vit dans CountryRulesResolver, plus ici.
        // L'itérable est matérialisé une fois : partagé entre ce résolveur et
        // PaySlipValueCalculator (même jeu de règles pour le bulletin).
        $rules = is_array($countryRules) ? $countryRules : iterator_to_array($countryRules);
        $this->resolver = new CountryRulesResolver($rules);
        // Issue #1874 — audit des calculs : jamais null (repli direct si le
        // conteneur n'injecte pas le service, ex. app(PayrollCalculator::class)).
        $this->auditRecorder = $auditRecorder ?? new PayrollCalculationAuditRecorder;
        // #5591 : agrégats des entrées de travail délégués au service dédié
        // (jamais null — repli direct pour les instanciations hors conteneur).
        $this->workInputAggregator = $workInputAggregator ?? new PayrollWorkInputAggregator;
        // #5591 (slice 2) : valeurs de bulletin déléguées au service dédié —
        // même jeu de règles pays et même agrégateur (instance partagée).
        $this->slipValues = new PaySlipValueCalculator($rules, $publicHolidayService, $this->workInputAggregator);
        // #5591 (slice 3) : régularisation déléguée au service dédié — le
        // calcul des valeurs (PaySlipValueCalculator) est partagé.
        $this->regularization = new PayrollRegularizationCalculator($this->slipValues);
    }

    /**
     * #5591 — façade publique conservée (API stable) : agrégats des entrées
     * de travail délégués à PayrollWorkInputAggregator (implémentation
     * extraite du god-object). Les appelants externes (tests, services)
     * continuent d'appeler PayrollCalculator::collectWorkInputs().
     */
    /**
     * @param  array{overtime_hours?: float}|null                          $attendanceAgg
     * @param  array{paid_leave_days?: float, unpaid_leave_days?: float}|null $leaveAgg
     * @return array{overtime_hours: float, paid_leave_days: float, unpaid_leave_days: float}
     */
    public function collectWorkInputs(
        PayrollRun $run,
        Employee $employee,
        ?array $attendanceAgg = null,
        ?array $leaveAgg = null
    ): array {
        return $this->workInputAggregator->collectWorkInputs($run, $employee, $attendanceAgg, $leaveAgg);
    }

    /**
     * Résout les règles de paie d'un pays via le résolveur unique (#1868).
     *
     * @throws UnsupportedCountryRulesException si le pays n'est pas enregistré
     */
    public function getRules(string $countryCode): CountryRulesContract
    {
        return $this->resolver->resolve($countryCode);
    }

    /**
     * Résolveur unique des règles pays (MULTI-PAYS #1868) — expose les
     * scopes entreprise/période pour les services qui en ont besoin.
     */
    public function rulesResolver(): CountryRulesResolver
    {
        return $this->resolver;
    }

    /**
     * Issue #1874 — orchestrateur de calcul d'un run de paie :
     *  1. identifiant de corrélation (généré à la première passe, persisté
     *     sur le run, propagé aux logs via Log::withContext) ;
     *  2. exécution du calcul (corps historique dans executeCalculateRun) ;
     *  3. enregistrement d'audit (payroll_calculation_audits) — succès ou
     *     échec mappé (rule_missing / provider_error), jamais de données
     *     individuelles ni de secrets (docs/payroll/AUDIT.md).
     *
     * Un run verrouillé (clôture comptable) ne peut plus être recalculé —
     * aucune modification silencieuse après clôture (F-11) : le garde reste
     * en tête, hors du périmètre audité (ce n'est pas un calcul).
     */
    public function calculateRun(PayrollRun $run): PayrollRun
    {
        if ($run->status === PayrollRun::STATUS_LOCKED) {
            throw new PayrollRunLockedException('Payroll run is locked (closing done). Unlock with reason first.');
        }

        $correlationId = $run->correlation_id;
        if ($correlationId === null) {
            // Issue #1874 : corrélation requête ↔ calcul — on reprend le
            // header X-Correlation-ID/X-Request-Id de la REQUÊTE COURANTE
            // (frais par requête via RequestIdMiddleware), sinon UUID frais.
            // NB : ne pas utiliser correlation_id() ici — sa valeur est liée
            // au conteneur et peut être STALE entre deux tests PHPUnit du
            // même process → deux runs avec le même ID → violation de la
            // contrainte unique payroll_runs.correlation_id (#2551 cause 8).
            $header = request()->header('X-Correlation-ID') ?: request()->header('X-Request-Id');
            $correlationId = is_string($header) && $header !== '' ? $header : (string) Str::uuid();
            $run->forceFill(['correlation_id' => $correlationId])->save();
        } else {
            // Recalcul d'un run déjà calculé : chaque tentative de calcul est
            // un NOUVEAU calcul → nouveau correlation_id. Sans cela, le 2e
            // insert d'audit viole la contrainte unique
            // payroll_calculation_audits.correlation_id et le recalcul
            // (flux légitime, cf. test "rebuilds slips on recalculation")
            // échoue en QueryException 25P02.
            $correlationId = (string) Str::uuid();
            $run->forceFill(['correlation_id' => $correlationId])->save();
        }
        // Corrélation des logs de ce calcul (issue #1874).
        Log::withContext(['correlation_id' => $correlationId]);

        try {
            $result = $this->executeCalculateRun($run);

            $this->auditRecorder->recordRunSuccess($result, $correlationId);

            return $result;
        } catch (UnsupportedCountryRulesException $exception) {
            // Pays sans règles enregistrées → statut rule_missing (observable).
            $this->auditRecorder->recordRunFailure(
                $run,
                $correlationId,
                PayrollCalculationAudit::STATUS_RULE_MISSING,
                $exception
            );

            throw $exception;
        } catch (\Throwable $exception) {
            // Tout autre échec moteur/stockage → provider_error (observable).
            $this->auditRecorder->recordRunFailure(
                $run,
                $correlationId,
                PayrollCalculationAudit::STATUS_PROVIDER_ERROR,
                $exception
            );

            throw $exception;
        }
    }

    /**
     * Corps historique de calculateRun() (issues #1868/#1871/#1983) — ne
     * contient ni corrélation ni audit : uniquement le calcul lui-même.
     */
    private function executeCalculateRun(PayrollRun $run): PayrollRun
    {
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
        // Issue #1871 — version/identifiant/période des règles EFFECTIVES,
        // persistées sur le run et chaque bulletin (audit et re-calcul).
        $rulesVersion = $rules->rulesVersion();
        $rulesIdentifier = (new \ReflectionClass($rules))->getShortName();
        $rulesPeriod = $run->period_start->toDateString();

        // Issue #1983 — un run de régularisation ne recalcule PAS des bulletins
        // complets : il produit un DIFFÉRENTIEL par employé affecté (corrigé −
        // original), en référence au run original verrouillé.
        if ($run->type === PayrollRun::TYPE_REGULARIZATION) {
            return $this->regularization->calculateRegularizationRun($run, $rules);
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

        // Issue #2687 (T026) : agrégats BATCH (attendance + congés) pour tous
        // les employés en ~3 requêtes au lieu de ~5 par employé (1000 employés
        // ≈ 15 requêtes au lieu de 5000+). Les méthodes par-employé restent
        // inchangées quand l'agrégat n'est pas fourni (repli identique).
        [$attendanceAgg, $leaveAgg] = $this->workInputAggregator->aggregateWorkInputs($run, $employees);

        DB::transaction(function () use (
            $run,
            $employees,
            $structures,
            $defaultStructure,
            $rules,
            $rulesVersion,
            $rulesIdentifier,
            $rulesPeriod,
            $attendanceAgg,
            $leaveAgg
        ) {
            $run->paySlips()->delete();

            $totalGross = 0.0;
            $totalDeductions = 0.0;
            $totalNet = 0.0;
            $totalEmployerCost = 0.0;

            foreach ($employees as $employee) {
                // Issue #1587 : la structure salariale est résolue PAR EMPLOYÉ
                // (employees.salary_structure_id) avec repli sur la structure
                // par défaut de l'entreprise si non affecté — comportement
                // historique (première structure active) préservé en fallback.
                /** @var SalaryStructure|null $structure */
                $structure = $employee->salary_structure_id !== null
                    ? ($structures->get($employee->salary_structure_id) ?? $defaultStructure)
                    : $defaultStructure;

                if (!$structure) {
                    continue;
                }

                $slip = $this->calculateSlip(
                    $run,
                    $employee,
                    $structure,
                    $rules,
                    $rulesVersion,
                    $rulesIdentifier,
                    $rulesPeriod,
                    $attendanceAgg[$employee->id] ?? null,
                    $leaveAgg[$employee->id] ?? null
                );

                $totalGross += (float) $slip->gross_salary;
                $totalDeductions += (float) $slip->total_deductions;
                $totalNet += (float) $slip->net_salary;
                $totalEmployerCost += (float) $slip->total_cost;
            }

            $run->update([
                'status' => 'calculated',
                // Issue #1874 — version/identifiant/période des règles
                // EFFECTIVES persistées sur le run (l'audit et les re-calculs
                // historiques en ont besoin ; seuls les bulletins les
                // portaient avant — cf. #1871/#1874).
                'rules_version' => $rulesVersion,
                'rules_identifier' => $rulesIdentifier,
                'rules_period' => $rulesPeriod,
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

    /**
     * @param  array{distinct_days?: int, overtime_hours?: float}|null  $attendanceAgg
     * @param  array{paid_leave_days?: float, unpaid_leave_days?: float}|null  $leaveAgg
     */
    private function calculateSlip(
        PayrollRun $run,
        Employee $employee,
        SalaryStructure $structure,
        CountryRulesContract $rules,
        string $rulesVersion,
        string $rulesIdentifier,
        string $rulesPeriod,
        ?array $attendanceAgg = null,
        ?array $leaveAgg = null
    ): PaySlip {
        $values = $this->slipValues->computeSlipValues($run, $employee, $structure, $rules, $attendanceAgg, $leaveAgg);

        /** @var PaySlip $slip */
        $slip = PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'rules_version' => $rulesVersion,
            'rules_period' => $rulesPeriod,
            'rules_identifier' => $rulesIdentifier,
            'gross_salary' => $values['gross_salary'],
            'total_deductions' => $values['total_deductions'],
            'net_salary' => $values['net_salary'],
            'employer_contributions' => $values['employer_contributions'],
            'total_cost' => $values['total_cost'],
            'working_days' => $values['working_days'],
            'actual_days_worked' => $values['actual_days_worked'],
            'overtime_hours' => $values['overtime_hours'],
            'paid_leave_days' => $values['paid_leave_days'],
            'unpaid_leave_days' => $values['unpaid_leave_days'],
            'public_holiday_days' => $values['public_holiday_days'],
            'has_attendance_data' => $values['has_attendance_data'],
            'status' => 'calculated',
        ]);

        foreach ($values['lines'] as $line) {
            PaySlipLine::create(array_merge($line, ['pay_slip_id' => $slip->id]));
        }

        return $slip;
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

    /**
     * Programme FOCUS (F-20) — entrées de travail réelles d'un employé sur la
     * période du run : heures sup (pointage) + jours de congés approuvés.
     *
     * Sources :
     *  - AttendanceLog.overtime_hours (somme des logs non annulés/rejetés) ;
     *  - Absence approuvées (status=approved), ventilées payées (is_paid) /
     *    non payées via AbsenceType.
     *
     * @param  array{distinct_days?: int, overtime_hours?: float}|null  $attendanceAgg
     * @param  array{paid_leave_days?: float, unpaid_leave_days?: float}|null  $leaveAgg
     * @return array{overtime_hours: float, paid_leave_days: float, unpaid_leave_days: float}
     */

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
        $proratedPay = $this->slipValues->computeProratedBase($monthlyBase, $workingDays, $proratedDays);
        $leaveIndemnity = $this->slipValues->computeLeaveIndemnity($monthlyBase, $unpaidLeaveDays, $workingDays, $referenceGross12Months);
        $noticePay = $noticeDays > 0.0
            // L'indemnité de préavis (jours de préavis non effectués) n'est pas
            // plafonnée par le nombre de jours ouvrés du mois : un préavis de
            // 30 jours calendaires se paie 30/22e du salaire mensuel (golden
            // F-08, « avec préavis non effectué » → 261 818,18 DZD).
            ? round($monthlyBase * ($noticeDays / max(1.0, $workingDays)), 2)
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

    /**
     * #5591 (slice 2) — façades publiques conservées (API stable) : le calcul
     * des valeurs de bulletin est délégué à PaySlipValueCalculator
     * (implémentation extraite du god-object). Les appelants externes (tests
     * golden, simulateurs, services) continuent d'appeler PayrollCalculator.
     */
    /**
     * @param  array{distinct_days?: int, overtime_hours?: float}|null $attendanceAgg
     * @return array{working_days: float, actual_days_worked: float, overtime_hours: float, has_attendance_data: bool}
     */
    public function computeWorkedDays(PayrollRun $run, Employee $employee, ?array $attendanceAgg = null): array
    {
        return $this->slipValues->computeWorkedDays($run, $employee, $attendanceAgg);
    }

    public function computeProratedBase(float $baseSalary, float $workingDays, float $actualDays): float
    {
        return $this->slipValues->computeProratedBase($baseSalary, $workingDays, $actualDays);
    }

    public function computeOvertimePay(
        float $baseSalary,
        float $overtimeHours,
        int $standardRateHours = 10,
        ?CountryRulesInterface $rules = null
    ): float {
        return $this->slipValues->computeOvertimePay($baseSalary, $overtimeHours, $standardRateHours, $rules);
    }

    public function computeSickLeaveAllowance(float $dailyReferenceWage, float $sickDays, CountryRulesContract $rules): float
    {
        return $this->slipValues->computeSickLeaveAllowance($dailyReferenceWage, $sickDays, $rules);
    }

    public function computeLeaveIndemnity(
        float $monthlyBase,
        float $leaveDays,
        float $workingDays,
        float $referenceGross12Months,
        float $accruedDaysTotal = 30.0
    ): float {
        return $this->slipValues->computeLeaveIndemnity($monthlyBase, $leaveDays, $workingDays, $referenceGross12Months, $accruedDaysTotal);
    }

    /**
     * @return array{
     *     social: array{employee: float, employer: float},
     *     taxable_gross: float,
     *     non_taxable_earnings: float,
     *     income_tax: float,
     *     bracket_tax: float,
     *     base_deductions: float,
     *     net_salary: float,
     *     total_cost: float,
     * }
     */
    public function computeNetBreakdown(
        float $grossEarnings,
        CountryRulesContract $rules,
        ?float $familyParts = null,
        float $nonTaxableEarnings = 0.0
    ): array {
        return $this->slipValues->computeNetBreakdown($grossEarnings, $rules, $familyParts, $nonTaxableEarnings);
    }

    /**
     * @return array<int, array{min: float, max: float|null, rate: float, taxable_amount: float, tax: float}>
     */
    public function slabTaxBreakdown(CountryRulesContract $rules, float $gross, float $taxBase, float $expectedTax): array
    {
        return $this->slipValues->slabTaxBreakdown($rules, $gross, $taxBase, $expectedTax);
    }
}
