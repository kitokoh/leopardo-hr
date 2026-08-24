<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface as CountryRulesContract;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunLockedException;
use App\Modules\Payroll\Domain\Exceptions\UnsupportedCountryRulesException;
use App\Modules\Payroll\Domain\Models\PayrollCalculationAudit;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Domain\Models\SalaryComponent;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AbstractCountryRules;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayrollCalculator
{
    /** Jours ouvrés standards mensuels (DZ) — docs/payroll/DZ_COMPLIANCE.md §5. */
    public const STANDARD_WORKING_DAYS = 22;

    /** Heures mensuelles de référence (base / 173,33 h). */
    public const MONTHLY_HOURS = 173.33;

    private CountryRulesResolver $resolver;

    private PayrollCalculationAuditRecorder $auditRecorder;

    /**
     * @param  iterable<CountryRulesInterface>  $countryRules  règles custom (tests) ; vide → résolveur par défaut
     */
    public function __construct(
        iterable $countryRules = [],
        private readonly ?PublicHolidayService $publicHolidayService = null,
        ?PayrollCalculationAuditRecorder $auditRecorder = null,
    ) {
        // MULTI-PAYS (#1868) : point d'entrée unique pour la résolution des
        // règles pays — la map vit dans CountryRulesResolver, plus ici.
        $this->resolver = new CountryRulesResolver($countryRules);
        // Issue #1874 — audit des calculs : jamais null (repli direct si le
        // conteneur n'injecte pas le service, ex. app(PayrollCalculator::class)).
        $this->auditRecorder = $auditRecorder ?? new PayrollCalculationAuditRecorder;
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
     * Issue #1869 — noyau de calcul commun à la simulation et au bulletin.
     *
     * C'est l'UNIQUE endroit qui assemble cotisations salariales, assiette
     * imposable, impôt sur le revenu, taxe de minimum fiscal (TRIMF/minimum
     * fiscal), net et coût employeur à partir des règles pays. La simulation
     * (CotisationSimulationController) et PayrollCalculator::calculateSlip()
     * passent par cette méthode : mêmes appels métier → mêmes montants pour
     * un même brut et un même contexte de règles (critère « la simulation et
     * le bulletin produisent les mêmes résultats pour un cas identique »).
     *
     * Politique d'arrondi (documentée docs/payroll/CALCULATION_CONTRACT.md) :
     * l'impôt est calculé sur l'assiette NON arrondie (brut − cotisations,
     * comme le bulletin) ; seuls les montants exposés sont arrondis à 2
     * décimales (demi au plus proche) ; le net a un plancher à 0.
     *
     * Issue #2220 — décomposition de l'impôt par tranche, alignée sur la
     * règle pays. L'assiette ET la convention (mensuelle/annualisée) varient
     * par pays (ex. CI : ITS 2024 sur le BRUT mensuel ; DZ : progressif sur
     * le net fiscal mensuel ; MA/FR : annualisé) : on évalue les 4 candidats
     * (assiette gross/taxable × convention mensuelle/annualisée) et on
     * retient celui dont le total est le plus proche de l'impôt réellement
     * calculé par le moteur (`calculateIncomeTax()`). La somme des tranches
     * converge ainsi vers l'impôt affiché (simulateur = bulletin).
     *
     * @return array<int, array{min: float, max: float|null, rate: float, taxable_amount: float, tax: float}>
     */
    public function slabTaxBreakdown(CountryRulesContract $rules, float $gross, float $taxBase, float $expectedTax): array
    {
        $candidates = [
            $this->progressiveSlabs($rules, $gross, 1.0),
            $this->progressiveSlabs($rules, $taxBase, 1.0),
            $this->progressiveSlabs($rules, $gross, 12.0),
            $this->progressiveSlabs($rules, $taxBase, 12.0),
        ];

        $best = $candidates[0];
        $bestDelta = PHP_FLOAT_MAX;
        foreach ($candidates as $candidate) {
            $delta = abs(array_sum(array_column($candidate, 'tax')) - $expectedTax);
            if ($delta < $bestDelta) {
                $best = $candidate;
                $bestDelta = $delta;
            }
        }

        return $best;
    }

    /**
     * @return array<int, array{min: float, max: float|null, rate: float, taxable_amount: float, tax: float}>
     */
    private function progressiveSlabs(CountryRulesContract $rules, float $taxBase, float $periods): array
    {
        $base = $taxBase * $periods;
        $bySlab = [];

        foreach ($rules->taxSlabs() as $slab) {
            $lowerBound = (float) $slab['min'];
            if ($lowerBound > 0) {
                $lowerBound -= 1;
            }
            $upperBound = $slab['max'] === null ? PHP_FLOAT_MAX : (float) $slab['max'];
            $taxableInSlab = min($base, $upperBound) - $lowerBound;
            if ($taxableInSlab <= 0) {
                continue;
            }
            $slabTax = round($taxableInSlab * ((float) $slab['rate'] / 100), 2);

            $bySlab[] = [
                'min' => (float) $slab['min'],
                'max' => $slab['max'],
                'rate' => (float) $slab['rate'],
                'taxable_amount' => round($taxableInSlab / $periods, 2),
                'tax' => round($slabTax / $periods, 2),
            ];
        }

        return $bySlab;
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
        $social = $rules->calculateSocialCharges($grossEarnings);
        // Issue #5241 (écart E3) — primes exonérées (SalaryComponent
        // is_taxable=false) : exclues de l'assiette IRG mais laissées dans
        // l'assiette CNAS (brut complet — position DZ documentée dans
        // DZ_COMPLIANCE.md §8, à confirmer par expert). Par défaut 0.0 →
        // comportement historique identique (simulation, autres pays).
        $taxableGross = $grossEarnings - $nonTaxableEarnings - $social['employee'];
        // Même appel que calculateSlip() : le 3e argument (grossForAbatement)
        // porte le brut réel aux règles qui en ont besoin (CM #1821).
        $incomeTax = $rules->calculateIncomeTax($taxableGross, 12, $grossEarnings);
        $bracketTax = $rules->calculateBracketTax($grossEarnings);

        // Issue #2117 — RICF (réduction d'impôt pour charges de famille) :
        // imputable sur l'impôt BRUT (net = max(0, impôt − réduction)),
        // montant mensuel décidé par la règle pays selon les parts fiscales
        // du salarié (défaut 1 part → réduction nulle → aucun changement).
        $incomeTax = round(max(0.0, $incomeTax - $rules->familyTaxReduction($familyParts ?? 1.0)), 2);

        // Issue #1934 — la règle pays décide de la combinaison IR/taxe de
        // minimum fiscal (défaut : additive ; SN : max(IR, TRIMF)).
        $baseDeductions = $social['employee'] + $rules->combineMinimumFiscalTax($incomeTax, $bracketTax);

        return [
            'social' => $social,
            'taxable_gross' => $taxableGross,
            'non_taxable_earnings' => $nonTaxableEarnings,
            'income_tax' => $incomeTax,
            'bracket_tax' => $bracketTax,
            'base_deductions' => $baseDeductions,
            'net_salary' => round(max(0.0, $grossEarnings - $baseDeductions), 2),
            'total_cost' => round($grossEarnings + $social['employer'], 2),
        ];
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
            return $this->calculateRegularizationRun($run, $rules);
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
        [$attendanceAgg, $leaveAgg] = $this->aggregateWorkInputs($run, $employees);

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

                if (! $structure) {
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
     * Issue #1983 — calcul d'un run de régularisation (type=regularization) :
     * DIFFÉRENTIEL par employé affecté, jamais un bulletin complet.
     *
     * Périmètre : les employés ayant un bulletin dans le run ORIGINAL
     * (verrouillé) — les départs en cours de période sont donc couverts et les
     * embauchés après la période exclus (aucun bulletin original).
     *
     * Valeur corrigée : recalcul complet du bulletin pour la période d'origine
     * avec les règles pays résolues asOf ($run->period_start == période
     * d'origine) et la structure salariale/le salaire de base ACTUELS de
     * l'employé (pas d'historique de structures — limite documentée). Les
     * entrées de travail (présences, heures sup., congés) sont celles
     * calculées au moment du run de régularisation (données actuelles).
     *
     * Delta = corrigé − original (par ligne et par champ). Un employé dont le
     * delta est nul ne reçoit AUCUN bulletin. Chaque bulletin de
     * régularisation référence son original (`original_slip_id`).
     *
     * @throws \RuntimeException si le run original manque ou n'est pas verrouillé
     */
    private function calculateRegularizationRun(PayrollRun $run, CountryRulesContract $rules): PayrollRun
    {
        /** @var PayrollRun|null $original */
        $original = $run->originalRun;
        if ($original === null) {
            throw new \RuntimeException('Un run de régularisation doit référencer son run original (original_run_id).');
        }
        if ($original->status !== PayrollRun::STATUS_LOCKED) {
            throw new \RuntimeException('Le run original doit être verrouillé pour calculer un différentiel de régularisation.');
        }

        /** @var Collection<int, SalaryStructure> $structuresCollection */
        $structuresCollection = SalaryStructure::query()
            ->where('company_id', $run->company_id)
            ->where('country_code', $run->country_code)
            ->where('active', true)
            ->with('components')
            ->get();

        /** @var Collection<int|string, SalaryStructure> $structures */
        $structures = $structuresCollection->keyBy('id');

        /** @var SalaryStructure|null $defaultStructure */
        $defaultStructure = $structures->first();

        // Issue #2221 : version/identifiant/période des règles EFFECTIVES
        // persistées aussi sur les runs de régularisation (promesse #1871).
        $rulesVersion = $rules->rulesVersion();
        $rulesIdentifier = (new \ReflectionClass($rules))->getShortName();
        $rulesPeriod = $run->period_start->toDateString();

        DB::transaction(function () use ($run, $original, $structures, $defaultStructure, $rules): void {
            // Recalcul idempotent : on repart de zéro (aucune double application).
            $run->paySlips()->delete();

            $totalGross = 0.0;
            $totalDeductions = 0.0;
            $totalNet = 0.0;
            $totalEmployerCost = 0.0;

            /** @var Collection<int, PaySlip> $originalSlips */
            $originalSlips = $original->paySlips()->with(['employee', 'lines'])->get();

            foreach ($originalSlips as $originalSlip) {
                /** @var Employee|null $employee */
                $employee = $originalSlip->employee;
                if ($employee === null) {
                    continue;
                }

                // Embauché APRÈS la période → aucun bulletin (garde défensive :
                // sans bulletin original, l'employé n'apparaît pas ici).
                if ($employee->contract_start !== null && $run->period_end < $employee->contract_start) {
                    continue;
                }

                /** @var SalaryStructure|null $structure */
                $structure = $employee->salary_structure_id !== null
                    ? ($structures->get($employee->salary_structure_id) ?? $defaultStructure)
                    : $defaultStructure;

                if ($structure === null) {
                    continue;
                }

                $corrected = $this->computeSlipValues($run, $employee, $structure, $rules);

                $delta = [
                    'gross_salary' => round($corrected['gross_salary'] - (float) $originalSlip->gross_salary, 2),
                    'total_deductions' => round($corrected['total_deductions'] - (float) $originalSlip->total_deductions, 2),
                    'net_salary' => round($corrected['net_salary'] - (float) $originalSlip->net_salary, 2),
                    'employer_contributions' => round($corrected['employer_contributions'] - (float) $originalSlip->employer_contributions, 2),
                    'total_cost' => round($corrected['total_cost'] - (float) $originalSlip->total_cost, 2),
                ];

                // Aucun changement pour cet employé → pas de bulletin.
                if (abs($delta['gross_salary']) < 0.005
                    && abs($delta['total_deductions']) < 0.005
                    && abs($delta['net_salary']) < 0.005
                    && abs($delta['total_cost']) < 0.005) {
                    continue;
                }

                /** @var PaySlip $slip */
                $slip = PaySlip::create([
                    'payroll_run_id' => $run->id,
                    'company_id' => $run->company_id,
                    'employee_id' => $employee->id,
                    'period_start' => $run->period_start,
                    'period_end' => $run->period_end,
                    'gross_salary' => $delta['gross_salary'],
                    'total_deductions' => $delta['total_deductions'],
                    'net_salary' => $delta['net_salary'],
                    'employer_contributions' => $delta['employer_contributions'],
                    'total_cost' => $delta['total_cost'],
                    'working_days' => $corrected['working_days'],
                    'actual_days_worked' => $corrected['actual_days_worked'],
                    'overtime_hours' => $corrected['overtime_hours'],
                    // Issue #5245 — snapshot des entrées congés/absences/fériés
                    // du calcul corrigé (transparence du différentiel).
                    'paid_leave_days' => $corrected['paid_leave_days'],
                    'unpaid_leave_days' => $corrected['unpaid_leave_days'],
                    'public_holiday_days' => $corrected['public_holiday_days'],
                    'has_attendance_data' => $corrected['has_attendance_data'],
                    'status' => 'calculated',
                    'original_slip_id' => $originalSlip->id,
                ]);

                $this->createDeltaLines($slip, $corrected['lines'], $originalSlip);

                $totalGross += $delta['gross_salary'];
                $totalDeductions += $delta['total_deductions'];
                $totalNet += $delta['net_salary'];
                $totalEmployerCost += $delta['total_cost'];
            }

            $run->update([
                'status' => 'calculated',
                // Issue #1871 — mêmes règles EFFECTIVES que le run standard :
                // l'audit lit rules_version/rules_identifier depuis le run.
                'rules_version' => $rules->rulesVersion(),
                'rules_identifier' => (new \ReflectionClass($rules))->getShortName(),
                'rules_period' => $run->period_start->toDateString(),
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
     * Issue #1983 — lignes du bulletin de régularisation : différentiel
     * corrigé − original par libellé (zéro exclu).
     *
     * @param  array<int, array<string, mixed>>  $correctedLines
     */
    private function createDeltaLines(PaySlip $slip, array $correctedLines, PaySlip $originalSlip): void
    {
        /** @var Collection<string, PaySlipLine> $originalLinesByName */
        $originalLinesByName = $originalSlip->lines->keyBy('name');

        $order = 0;
        foreach ($correctedLines as $correctedLine) {
            /** @var string $lineName */
            $lineName = $correctedLine['name'];
            /** @var PaySlipLine|null $originalLine */
            $originalLine = $originalLinesByName->get($lineName);

            /** @var int|float $correctedAmount */
            $correctedAmount = $correctedLine['amount'];
            $originalAmount = $originalLine !== null ? $originalLine->amount : 0.0;

            $deltaAmount = round((float) $correctedAmount - (float) $originalAmount, 2);
            if (abs($deltaAmount) < 0.005) {
                continue;
            }

            /** @var int|float|null $correctedBase */
            $correctedBase = $correctedLine['base_amount'] ?? 0.0;
            $originalBase = $originalLine !== null ? $originalLine->base_amount : 0.0;

            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => $lineName,
                'type' => $correctedLine['type'],
                'base_amount' => round((float) $correctedBase - (float) $originalBase, 2),
                'rate' => $correctedLine['rate'] ?? null,
                'amount' => $deltaAmount,
                'order' => $order++,
            ]);
        }
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
        $values = $this->computeSlipValues($run, $employee, $structure, $rules, $attendanceAgg, $leaveAgg);

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
     * Issue #1983 — calcule les VALEURS d'un bulletin SANS persister.
     * Utilisé par calculateSlip() (bulletin standard) et par
     * calculateRegularizationRun() (valeur corrigée du différentiel).
     *
     *
     * @param  array{distinct_days?: int, overtime_hours?: float}|null  $attendanceAgg
     * @param  array{paid_leave_days?: float, unpaid_leave_days?: float}|null  $leaveAgg
     * @return array{
     *     gross_salary: float,
     *     total_deductions: float,
     *     net_salary: float,
     *     employer_contributions: float,
     *     total_cost: float,
     *     working_days: float,
     *     actual_days_worked: float,
     *     overtime_hours: float,
     *     paid_leave_days: float,
     *     unpaid_leave_days: float,
     *     public_holiday_days: float,
     *     has_attendance_data: bool,
     *     lines: list<array<string, mixed>>,
     * }
     */
    private function computeSlipValues(
        PayrollRun $run,
        Employee $employee,
        SalaryStructure $structure,
        CountryRulesContract $rules,
        ?array $attendanceAgg = null,
        ?array $leaveAgg = null
    ): array {
        $baseSalary = $structure->base_salary;
        $worked = $this->computeWorkedDays($run, $employee, $attendanceAgg);
        $inputs = $this->collectWorkInputs($run, $employee, $attendanceAgg, $leaveAgg);

        // Jours d'absence (payés ou non) retirés des jours travaillés ;
        // les congés payés sont compensés par l'indemnité (F-07).
        //
        // F-20 (#1919) : quand la présence RÉELLE est renseignée
        // (has_attendance_data = true), le décompte des jours distincts vient
        // des logs de présence — les jours de congé y sont DÉJÀ exclus
        // (statuts 'leave' non comptés, absence = pas de log de présence).
        // Re-soustraire les congés ici DOUBLE-déduirait : 17 jours présents +
        // 5 jours de congé payé → 12/22 payés au lieu de 22/22
        // (sous-paiement silencieux, revue lead #1816).
        //  - congés PAYÉS : payés via l'indemnité F-07 (ligne earning) ;
        //  - congés SANS SOLDE : absents du décompte (non payés) — aucune
        //    déduction supplémentaire nécessaire.
        // Le décompte réel est plafonné aux jours ouvrés standards : les
        // pointages week-end/heures sup ne gonflent pas la base (l'HS est
        // payée séparément via overtime_hours).
        // Le fallback (sans logs, has_attendance_data = false) conserve le
        // comportement historique : le prorata contrat inclut les jours de
        // congé → déduction nécessaire (compensée par l'indemnité F-07).
        if ($worked['has_attendance_data']) {
            $worked['actual_days_worked'] = min(
                (float) $worked['working_days'],
                max(0.0, $worked['actual_days_worked'])
            );
        } else {
            $leaveDays = $inputs['paid_leave_days'] + $inputs['unpaid_leave_days'];
            $worked['actual_days_worked'] = max(0.0, $worked['actual_days_worked'] - $leaveDays);
        }
        $worked['overtime_hours'] = $inputs['overtime_hours'];

        $basePaid = $this->computeProratedBase($baseSalary, $worked['working_days'], $worked['actual_days_worked']);
        // #5266 — les paliers LÉGAUX du pays (overtimeRateTiers) pilotent le
        // calcul des HS dès que les règles du pays sont disponibles.
        $overtimePay = $this->computeOvertimePay($baseSalary, $worked['overtime_hours'], 10, $rules);
        $leaveIndemnity = $inputs['paid_leave_days'] > 0.0
            ? $this->computeLeaveIndemnity(
                $baseSalary,
                $inputs['paid_leave_days'],
                $worked['working_days'],
                $this->referenceGross12Months($run, $employee, $baseSalary),
                $this->accruedLeaveDays($run, $employee)
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
        // Issue #5241 (écart E3) — montant cumulé des composants de salaire
        // NON IMPOSABLES (is_taxable=false) : ajoutés au brut (assiette CNAS
        // complète) mais exclus de l'assiette IRG via computeNetBreakdown().
        $nonTaxableEarnings = 0.0;
        foreach ($components as $component) {
            /** @var SalaryComponent $component */
            if ($component->type !== 'earning') {
                continue;
            }
            $amount = $this->computeComponentAmount($component, $baseSalary, $grossEarnings);
            $grossEarnings += $amount;
            if (! $component->is_taxable) {
                $nonTaxableEarnings += $amount;
            }
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

        // ZONE-INFRA (#1820) — 13ème mois légal obligatoire : injecté comme
        // ligne earning du mois de décembre, donc soumis aux cotisations et à
        // l'impôt (traitement 'fully_taxable' par défaut — un pays qui
        // l'étale sur l'année pourra surcharger thirteenthMonthTaxTreatment()).
        if ($rules->thirteenthMonthMandatory() && (int) $run->period_start->month === 12) {
            $grossEarnings += $basePaid;
            $lines[] = [
                'name' => '13ème mois',
                'type' => 'earning',
                'base_amount' => $basePaid,
                'rate' => null,
                'amount' => $basePaid,
                'order' => $order++,
            ];
        }

        // ZONE-INFRA (#1820) — allocations familiales par enfant à charge :
        // ligne earning injectée quand la règle pays définit un montant ET que
        // l'employé expose children_count > 0 (colonne non câblée côté
        // provisioning pour l'instant — le mécanisme est prêt et inerte tant
        // qu'aucune règle pays ne retourne un montant).
        $childrenCount = $employee->getAttribute('children_count');
        if ($rules->familyAllowancePerChild() > 0.0 && is_numeric($childrenCount) && (int) $childrenCount > 0) {
            $allowance = round($rules->familyAllowancePerChild() * (int) $childrenCount, 2);
            $grossEarnings += $allowance;
            $lines[] = [
                'name' => 'Allocations familiales',
                'type' => 'earning',
                'base_amount' => (int) $childrenCount,
                'rate' => null,
                'amount' => $allowance,
                'order' => $order++,
            ];
        }

        // Issue #1869 — noyau de calcul commun simulation/bulletin : les
        // mêmes appels métier (calculateSocialCharges, calculateIncomeTax,
        // calculateBracketTax) servent la simulation ET le bulletin, garantie
        // que les deux produisent exactement les mêmes montants pour un même
        // brut et un même contexte de règles.
        // Issue #2117 — parts fiscales du salarié (RICF) portées par
        // `employees.family_parts` (défaut moteur 1 part → réduction nulle) ;
        // même pattern lecture attribut que children_count (allocations
        // familiales, ZONE-INFRA #1820).
        $breakdown = $this->computeNetBreakdown($grossEarnings, $rules, $this->familyPartsOf($employee), $nonTaxableEarnings);
        $social = $breakdown['social'];

        $lines[] = [
            'name' => 'Cotisations salariales',
            'type' => 'deduction',
            'base_amount' => $grossEarnings,
            'rate' => null,
            'amount' => $social['employee'],
            'order' => $order++,
        ];

        // ZONE-INFRA (#1820) — taxe de minimum fiscal (TRIMF SN, minimum
        // fiscal CI...) : déduction forfaitaire par tranche sur le brut,
        // ajoutée quand la règle pays la définit (> 0). Le libellé de ligne
        // est fourni par la règle pays (CI #1825 : « Contribution Nationale
        // (CN) » au lieu de « Taxe de minimum fiscal »).
        $incomeTax = $breakdown['income_tax'];
        $bracketTax = $breakdown['bracket_tax'];
        $bracketTaxLabel = $rules->flatPayrollTaxLabel();

        // Issue #1934 — mécanisme légal « max(IR, TRIMF) » (Sénégal) : la
        // règle combine les deux via combineMinimumFiscalTax(). Quand la
        // combinaison n'est pas additive, on n'affiche que la ligne gagnante
        // pour que le bulletin reste explicable (somme des lignes de
        // déduction = total déduit).
        $shownIncomeTax = $incomeTax;
        $shownBracketTax = $bracketTax;
        if ($rules->combineMinimumFiscalTax($incomeTax, $bracketTax) !== $incomeTax + $bracketTax) {
            if ($incomeTax >= $bracketTax) {
                $shownBracketTax = 0.0;
            } else {
                $shownIncomeTax = 0.0;
            }
        }

        $lines[] = [
            'name' => 'Impot sur le revenu',
            'type' => 'deduction',
            'base_amount' => $breakdown['taxable_gross'],
            'rate' => null,
            'amount' => $shownIncomeTax,
            'order' => $order++,
        ];

        if ($shownBracketTax > 0.0) {
            $lines[] = [
                'name' => $bracketTaxLabel,
                'type' => 'deduction',
                'base_amount' => $grossEarnings,
                'rate' => null,
                'amount' => $shownBracketTax,
                'order' => $order++,
            ];
        }

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

        // Déductions totales = base commune (cotisations salariales + impôt +
        // taxe de minimum fiscal) + composants de déduction personnalisés.
        // La boucle exclut les lignes déjà comptées dans la base (la taxe
        // forfaitaire par son libellé RÉEL — « Taxe de minimum fiscal » ou
        // « Contribution Nationale (CN) » pour CI #1825 — sinon double
        // déduction sur les bulletins CI).
        $totalDeductions = $breakdown['base_deductions'];
        foreach ($lines as $line) {
            if ($line['type'] === 'deduction'
                && $line['name'] !== 'Cotisations salariales'
                && $line['name'] !== 'Impot sur le revenu'
                && $line['name'] !== $bracketTaxLabel) {
                $totalDeductions += $line['amount'];
            }
        }

        $netSalary = round(max(0, $grossEarnings - $totalDeductions), 2);
        $totalCost = $breakdown['total_cost'];

        return [
            'gross_salary' => round($grossEarnings, 2),
            'total_deductions' => round($totalDeductions, 2),
            'net_salary' => $netSalary,
            'employer_contributions' => round($social['employer'], 2),
            'total_cost' => $totalCost,
            'working_days' => $worked['working_days'],
            'actual_days_worked' => $worked['actual_days_worked'],
            'overtime_hours' => $worked['overtime_hours'],
            // Issue #5245 — snapshot des entrées de travail utilisées par le
            // calcul (congés payés pris, congés sans solde, jours fériés
            // payés) : persisté sur le bulletin pour l'affichage du détail
            // par employé dans la simulation du run et l'audit paie.
            'paid_leave_days' => $inputs['paid_leave_days'],
            'unpaid_leave_days' => $inputs['unpaid_leave_days'],
            'public_holiday_days' => $this->publicHolidayDaysInPeriod($run),
            'has_attendance_data' => $worked['has_attendance_data'],
            'lines' => $lines,
        ];
    }

    /**
     * Issue #5245 — jours fériés PAYÉS tombant dans la période du run
     * (fériés nationaux + overrides entreprise, fusion islamique comprise).
     *
     * Les fériés sont déjà exclus des jours ouvrés (workingDaysBetween) : un
     * employé qui ne pointe pas un jour férié n'est PAS déduit — le férié est
     * payé par construction. Cette méthode rend ce fait EXPLICITE (détail
     * par employé dans la simulation du run) sans changer la mécanique.
     *
     * @return float nombre de jours fériés (fraction .0/.5 — arrondi affichage)
     */
    private function publicHolidayDaysInPeriod(PayrollRun $run): float
    {
        if ($this->publicHolidayService === null) {
            return 0.0;
        }

        $year = (int) $run->period_start->format('Y');
        $companyId = $run->company_id !== null ? (string) $run->company_id : null;

        try {
            $holidays = $this->publicHolidayService->getHolidays(
                (string) $run->country_code,
                $year,
                $companyId,
            );
        } catch (\Throwable) {
            // Garde défensive : un calendrier indisponible (ex. table absente
            // d'un schéma tenant historique) ne doit pas bloquer le calcul.
            return 0.0;
        }

        $start = $run->period_start->copy()->startOfDay();
        $end = $run->period_end->copy()->startOfDay();

        $days = 0.0;
        foreach ($holidays as $holiday) {
            $date = Carbon::parse((string) $holiday['date'])->startOfDay();
            if ($date->gte($start) && $date->lte($end)) {
                $days += 1.0;
            }
        }

        return $days;
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
     *
     * Issue #5266 (écart E2 de la spec `payroll-dz-100`) : quand les règles
     * du pays sont fournies, les paliers LÉGAUX pays
     * (CountryRulesInterface::overtimeRateTiers()) priment — ex. DZ :
     * majoration unique ≥ 50 % (loi 90-11 art. 32), FR : 8 h @ 25 % puis
     * 50 %, MA : 25 % unique... Le fallback historique « 25 % jusqu'à
     * $standardRateHours h/mois puis 50 % » reste actif pour les appels
     * sans règles (mécanique générique F-05 — docs/payroll/DZ_COMPLIANCE.md
     * §5, seuil conventionnel non confirmé).
     */
    public function computeOvertimePay(
        float $baseSalary,
        float $overtimeHours,
        int $standardRateHours = 10,
        ?CountryRulesInterface $rules = null
    ): float {
        if ($overtimeHours <= 0.0 || $baseSalary <= 0.0) {
            return 0.0;
        }

        // Issue #2685 (QA 2026-08-15) — le taux horaire était arrondi à 2
        // décimales AVANT les multiplicateurs 1.25/1.50 : sous-paiement
        // systématique (ex. base 100 000 → taux 576,85 au lieu de 576,879…).
        // La précision complète est conservée jusqu'à l'arrondi final.
        $hourlyRate = $baseSalary / self::MONTHLY_HOURS;

        $tiers = $rules?->overtimeRateTiers() ?? [];
        if ($tiers !== []) {
            return $this->computeOvertimePayByTiers($hourlyRate, $overtimeHours, $tiers);
        }

        $standard = min($overtimeHours, (float) $standardRateHours);
        $premium = max(0.0, $overtimeHours - (float) $standardRateHours);

        return round(($standard * $hourlyRate * 1.25) + ($premium * $hourlyRate * 1.50), 2);
    }

    /**
     * #5266 — applique les paliers légaux du pays à un volume d'heures
     * supplémentaires. Chaque palier consomme sa largeur (`up_to_hours`,
     * `null` = illimité) au multiplicateur indiqué ; l'arrondi n'intervient
     * qu'en sortie (précision #2685). Un volume non consommé par un palier
     * borné passe au palier suivant (les règles pays garantissent un palier
     * terminal `up_to_hours => null`).
     *
     * @param  array<int, array{up_to_hours: float|null, multiplier: float}>  $tiers
     */
    private function computeOvertimePayByTiers(float $hourlyRate, float $overtimeHours, array $tiers): float
    {
        $remaining = $overtimeHours;
        $pay = 0.0;

        foreach ($tiers as $tier) {
            if ($remaining <= 0.0) {
                break;
            }

            $width = $tier['up_to_hours'];
            $hoursInTier = $width === null ? $remaining : min($remaining, $width);
            $pay += $hoursInTier * $hourlyRate * $tier['multiplier'];
            $remaining -= $hoursInTier;
        }

        return round($pay, 2);
    }

    /**
     * Issue #5241 (écart E5) — indemnités journalières maladie / arrêt de
     * travail selon la politique pays (CountryRulesInterface::sickLeavePolicy).
     *
     * Règle (générique, valeurs pays dans la politique) :
     *  1. jours indemnisables = jours d'arrêt − délai de carence, plafonnés
     *     à max_paid_days ;
     *  2. pour chaque jour indemnisable, le taux de la 1ère tranche dont la
     *     plage [from_day, to_day] contient le JOUR D'ARRÊT (stoppage day =
     *     waiting_days + index IJ) s'applique sur le salaire journalier de
     *     référence ;
     *  3. arrondi final à 2 décimales.
     *
     * DZ (politique sourcée CNAS/loi 90-11, pilot) : carence 3 j, 50 % les
     * jours 1-15 puis 100 % à partir du 16e, max 180 j (maladie ordinaire).
     *
     * Consommée par le futur flux absence → paie (#5245) ; exposée et
     * verrouillée par golden tests (GoldenDzEngineCompletionTest) — aucun
     * changement de comportement du bulletin tant que l'appelant ne l'utilise
     * pas (politique inerte par défaut dans AbstractCountryRules).
     */
    public function computeSickLeaveAllowance(float $dailyReferenceWage, float $sickDays, CountryRulesContract $rules): float
    {
        $policy = $rules->sickLeavePolicy();
        if ($sickDays <= 0.0 || $dailyReferenceWage <= 0.0 || $policy['daily_allowance_rates'] === []) {
            return 0.0;
        }

        $waitingDays = max(0, (int) $policy['waiting_days']);
        $maxPaidDays = max(0, (int) $policy['max_paid_days']);
        $indemnifiedDays = min(max(0.0, $sickDays - $waitingDays), (float) $maxPaidDays);
        $eligibleDays = (int) floor($indemnifiedDays);

        $allowance = 0.0;
        for ($i = 1; $i <= $eligibleDays; $i++) {
            $stoppageDay = $waitingDays + $i;
            $rate = 0.0;
            foreach ($policy['daily_allowance_rates'] as $tier) {
                $from = max(1, (int) $tier['from_day']);
                $to = $tier['to_day'] === null ? PHP_INT_MAX : (int) $tier['to_day'];
                if ($stoppageDay >= $from && $stoppageDay <= $to) {
                    $rate = (float) $tier['rate'];
                    break;
                }
            }
            $allowance += $dailyReferenceWage * $rate;
        }

        return round($allowance, 2);
    }

    /**
     * Programme FOCUS (F-05/F-20) — jours travaillés sur la période du run.
     *
     * Recoupe le contrat de l'employé (contract_start / contract_end) avec la
     * période du run : prorata d'entrée/sortie en cours de mois.
     *
     * overtime_hours : implémenté — alimenté par le pointage (F-20) via
     * AttendanceLog.overtime_hours dans collectWorkInputs().
     *
     * actual_days_worked (F-20, #1816) : source réelle = jours DISTINCTS
     * pointés avec au moins un log valide sur la période (AttendanceLog,
     * statuts cancelled/rejected/incomplete exclus) ; fallback = prorata
     * contrat quand aucun log valide n'existe (comportement historique).
     * has_attendance_data indique quelle source a été utilisée.
     *
     * @param  array{distinct_days?: int, overtime_hours?: float}|null  $attendanceAgg
     * @return array{working_days: float, actual_days_worked: float, overtime_hours: float, has_attendance_data: bool}
     */
    public function computeWorkedDays(PayrollRun $run, Employee $employee, ?array $attendanceAgg = null): array
    {
        // Issue #1811 : jours ouvrés dynamiques par pays (fériés + jours de
        // repos hebdomadaire) au lieu de la constante 22. Fallback 22 si le
        // service n'est pas injecté ou si aucun férié n'est configuré.
        $periodStart = $run->period_start->copy()->startOfDay();
        $periodEnd = $run->period_end->copy()->startOfDay();

        $workingDays = $this->publicHolidayService !== null
            ? $this->publicHolidayService->workingDaysBetween(
                $periodStart,
                $periodEnd,
                (string) $run->country_code,
                holidays: null,
                companyId: $run->company_id,
                restDays: $this->weeklyRestDaysFor((string) $run->country_code),
            )
            : (float) self::STANDARD_WORKING_DAYS;

        // Garde-fou : ne jamais retourner 0 (période sans aucun jour ouvré
        // ou pays sans règles) — on retombe sur la constante historique.
        if ($workingDays <= 0.0) {
            $workingDays = (float) self::STANDARD_WORKING_DAYS;
        }

        $overlapStart = $periodStart->copy();
        if ($employee->contract_start !== null && $employee->contract_start->gt($periodStart)) {
            $overlapStart = $employee->contract_start->copy()->startOfDay();
        }

        $overlapEnd = $periodEnd->copy();
        if ($employee->contract_end !== null && $employee->contract_end->lt($periodEnd)) {
            $overlapEnd = $employee->contract_end->copy()->startOfDay();
        }

        // F-20 (#1816) — compter les jours distincts avec au moins 1 log
        // valide (source réelle de présence). #1919 : seuls les statuts de
        // PRÉSENCE (ontime/late) attestent un jour travaillé — les statuts
        // absent/leave/holiday/incomplete sont exclus (l'enum réel de
        // attendance_logs.status n'a pas cancelled/rejected). #1925 : garde
        // résolue via le search_path (`schemaTableExists`, CONVENTIONS
        // §2.6/#1613) au lieu de `Schema::hasTable()` qui ne voit que
        // `current_schema()` — en contexte multi-schéma (CI :
        // shared_tenants,public / local : public,shared_tenants) le garde nu
        // répondait faux à tort → repli silencieux sur le prorata du contrat
        // et `actual_days_worked` faux sans aucun signal (revue lead #1862).
        $distinctDays = 0;
        if ($attendanceAgg !== null && isset($attendanceAgg['distinct_days'])) {
            // Issue #2687 : valeur pré-agrégée en batch (executeCalculateRun).
            $distinctDays = (int) $attendanceAgg['distinct_days'];
        } elseif (schemaTableExists('attendance_logs')) {
            try {
                $distinctDays = (int) AttendanceLog::query()
                    ->where('company_id', $run->company_id)
                    ->where('employee_id', $employee->id)
                    ->whereBetween('date', [$run->period_start, $run->period_end])
                    ->whereNotIn('status', ['absent', 'leave', 'holiday', 'incomplete'])
                    ->distinct('date')
                    ->count('date');
            } catch (QueryException $e) {
                // Garde défensive : table partiellement migrée ou supprimée
                // entre la vérification et la requête → repli sur le prorata
                // (comportement historique pour les environnements sans
                // migration tenant, ex. golden tests purs). #2025 : le repli
                // n'est plus silencieux — journalisé pour observabilité.
                Log::warning('computeWorkedDays: repli prorata — requête attendance_logs en échec', [
                    'company_id' => $run->company_id,
                    'employee_id' => $employee->id,
                    'period_start' => $run->period_start->toDateString(),
                    'period_end' => $run->period_end->toDateString(),
                    'error' => $e->getMessage(),
                ]);
                $distinctDays = 0;
            }
        } else {
            // #2025 : table absente du search_path (CI : shared_tenants,public
            // vs local : public,shared_tenants — CONVENTIONS §2.6/#1613) →
            // repli prorata historique, désormais journalisé.
            Log::warning('computeWorkedDays: repli prorata — table attendance_logs absente du search_path', [
                'company_id' => $run->company_id,
                'employee_id' => $employee->id,
            ]);
        }

        $hasAttendanceData = $distinctDays > 0;
        // #1919 : le décompte réel est plafonné aux jours ouvrés standards —
        // les pointages week-end/heures sup ne gonflent pas la base (l'HS est
        // payée séparément via overtime_hours).
        $actualDays = $hasAttendanceData
            ? min($workingDays, (float) $distinctDays)
            : $this->contractProrata($periodStart, $periodEnd, $overlapStart, $overlapEnd, $workingDays);

        return [
            'working_days' => $workingDays,
            'actual_days_worked' => $actualDays,
            'overtime_hours' => 0.0,
            'has_attendance_data' => $hasAttendanceData,
        ];
    }

    /**
     * Jours de repos hebdomadaire du pays (ISO 1=lundi..7=dimanche).
     *
     * @return array<int, int>
     */
    private function weeklyRestDaysFor(string $countryCode): array
    {
        try {
            return $this->getRules($countryCode)->weeklyRestDays();
        } catch (\Throwable) {
            return [6, 7];
        }
    }

    /**
     * F-20 (#1816) — prorata calendaire de repli (fallback) quand aucun log
     * de pointage valide n'existe sur la période : ratio de chevauchement
     * contrat ↔ période appliqué aux jours ouvrés standards.
     */
    private function contractProrata(
        Carbon $periodStart,
        Carbon $periodEnd,
        Carbon $overlapStart,
        Carbon $overlapEnd,
        float $workingDays
    ): float {
        $periodDays = $periodStart->diffInDays($periodEnd) + 1;
        $overlapDays = max(0, $overlapStart->diffInDays($overlapEnd) + 1);

        $ratio = $periodDays > 0 ? min(1.0, $overlapDays / $periodDays) : 0.0;

        return round($workingDays * $ratio, 2);
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
     * F-07 (#1537) — salaires bruts validés des 12 mois précédant la période
     * du run (règle du 1/10ᵉ). Fallback : base mensuelle × 12 quand aucun
     * historique validé n'existe encore (premier cycle de paie).
     */
    private function referenceGross12Months(PayrollRun $run, Employee $employee, float $baseSalary): float
    {
        $twelveMonthsAgo = (clone $run->period_start)->subMonths(12);

        $slips = PaySlip::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            // Issue #2679 — les runs créent des bulletins 'calculated' (non
            // encore validés) : une entreprise qui ne valide jamais retombait
            // en silence sur base×12. Les deux statuts comptent.
            ->whereIn('status', ['calculated', 'validated'])
            ->where('period_start', '>=', $twelveMonthsAgo)
            ->where('period_start', '<', $run->period_start)
            ->get(['gross_salary', 'period_start']);

        if ($slips->isEmpty()) {
            return $baseSalary * 12.0;
        }

        $gross = (float) $slips->sum('gross_salary');

        // Période partielle (embauche en cours d'année) : on normalise sur 12
        // mois pour ne pas sous-évaluer le 1/10ᵉ (ex. 3 bulletins → gross × 12/3).
        $months = $slips
            ->map(fn ($slip) => $slip->period_start->format('Y-m'))
            ->unique()
            ->count();

        if ($months > 0 && $months < 12) {
            $gross = $gross * (12.0 / $months);
        }

        return $gross;
    }

    /**
     * F-07 (#1537) — jours de congés acquis par l'employé (LeaveBalance,
     * alimenté par `leave:accrue`) sur l'année de la période du run.
     * Fallback : 30 jours (acquisition légale DZ, congés payés annuels).
     */
    private function accruedLeaveDays(PayrollRun $run, Employee $employee): float
    {
        $year = (int) $run->period_start->format('Y');

        // Jours ACQUIS = solde restant + jours déjà pris (+ en attente) :
        // LeaveBalance.balance n'est que le RESTANT (décrémenté à l'approbation
        // d'un congé) — l'utiliser seul sous-évalue l'acquisition (F-07 #1537).
        $row = LeaveBalance::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->whereHas('absenceType', fn ($q) => $q->where('is_paid', true))
            ->selectRaw('SUM(balance + used + pending) as acquired')
            ->first();

        $days = $row !== null ? (float) $row->getAttribute('acquired') : 0.0;

        return $days > 0.0 ? $days : 30.0;
    }

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
     * @param  array{distinct_days?: int, overtime_hours?: float}|null  $attendanceAgg
     * @param  array{paid_leave_days?: float, unpaid_leave_days?: float}|null  $leaveAgg
     * @return array{overtime_hours: float, paid_leave_days: float, unpaid_leave_days: float}
     */
    public function collectWorkInputs(
        PayrollRun $run,
        Employee $employee,
        ?array $attendanceAgg = null,
        ?array $leaveAgg = null
    ): array {
        if ($attendanceAgg !== null && $leaveAgg !== null) {
            // Issue #2687 : agrégats batch (executeCalculateRun) — mêmes
            // valeurs que les requêtes par-employé ci-dessous.
            return [
                'overtime_hours' => (float) ($attendanceAgg['overtime_hours'] ?? 0.0),
                'paid_leave_days' => (float) ($leaveAgg['paid_leave_days'] ?? 0.0),
                'unpaid_leave_days' => (float) ($leaveAgg['unpaid_leave_days'] ?? 0.0),
            ];
        }

        $overtimeHours = AttendanceLog::query()
            ->where('company_id', $run->company_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$run->period_start, $run->period_end])
            ->where('overtime_hours', '>', 0)
            ->whereNotIn('status', ['cancelled', 'rejected', 'incomplete'])
            ->sum('overtime_hours');

        $paidLeave = $this->sumApprovedLeaveDays($run, $employee, true);
        $unpaidLeave = $this->sumApprovedLeaveDays($run, $employee, false);

        return [
            'overtime_hours' => (float) $overtimeHours,
            'paid_leave_days' => $paidLeave,
            'unpaid_leave_days' => $unpaidLeave,
        ];
    }

    /**
     * Issue #2687 (T026) — agrégats groupés pour TOUS les employés du run :
     * jours de présence distincts + heures sup (attendance_logs) et congés
     * approuvés payés/non payés (absences, clipping période identique à
     * sumApprovedLeaveDays). ~3 requêtes au total au lieu de ~5 par employé.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array{0: array<int, array{distinct_days?: int, overtime_hours?: float}>, 1: array<int, array{paid_leave_days?: float, unpaid_leave_days?: float}>}
     */
    private function aggregateWorkInputs(PayrollRun $run, Collection $employees): array
    {
        $attendance = [];
        $leave = [];

        if (schemaTableExists('attendance_logs')) {
            try {
                $distinct = AttendanceLog::query()
                    ->selectRaw('employee_id, COUNT(DISTINCT date) AS distinct_days')
                    ->where('company_id', $run->company_id)
                    ->whereBetween('date', [$run->period_start, $run->period_end])
                    ->whereNotIn('status', ['absent', 'leave', 'holiday', 'incomplete'])
                    ->groupBy('employee_id')
                    ->get();

                foreach ($distinct as $row) {
                    $attendance[(int) $row->employee_id]['distinct_days'] = (int) $row->distinct_days;
                }

                $overtime = AttendanceLog::query()
                    ->selectRaw('employee_id, COALESCE(SUM(overtime_hours), 0) AS overtime_hours')
                    ->where('company_id', $run->company_id)
                    ->whereBetween('date', [$run->period_start, $run->period_end])
                    ->where('overtime_hours', '>', 0)
                    ->whereNotIn('status', ['cancelled', 'rejected', 'incomplete'])
                    ->groupBy('employee_id')
                    ->get();

                foreach ($overtime as $row) {
                    $attendance[(int) $row->employee_id]['overtime_hours'] = (float) $row->overtime_hours;
                }
            } catch (QueryException $e) {
                // Repli par-employé (les méthodes gardent leur try/catch).
                Log::warning('aggregateWorkInputs: repli par-employé — attendance_logs en échec', [
                    'company_id' => $run->company_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $absences = Absence::query()
                ->where('company_id', $run->company_id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $run->period_end)
                ->whereDate('end_date', '>=', $run->period_start)
                ->with('absenceType:id,is_paid')
                ->get(['id', 'employee_id', 'absence_type_id', 'start_date', 'end_date', 'days_count']);

            $periodStart = $run->period_start->copy()->startOfDay();
            $periodEnd = $run->period_end->copy()->startOfDay();

            foreach ($absences as $absence) {
                if ($absence->end_date === null || $absence->absenceType === null) {
                    continue;
                }

                $overlapStart = $absence->start_date->copy()->max($periodStart);
                $overlapEnd = $absence->end_date->copy()->min($periodEnd);

                if ($overlapEnd->lt($overlapStart)) {
                    continue;
                }

                $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;
                $totalSpanDays = $absence->start_date->diffInDays($absence->end_date) + 1;

                $days = $totalSpanDays > 0
                    ? (float) $absence->days_count * ($overlapDays / $totalSpanDays)
                    : (float) $absence->days_count;

                $key = (int) $absence->employee_id;
                if ((bool) $absence->absenceType->is_paid) {
                    $leave[$key]['paid_leave_days'] = ($leave[$key]['paid_leave_days'] ?? 0.0) + $days;
                } else {
                    $leave[$key]['unpaid_leave_days'] = ($leave[$key]['unpaid_leave_days'] ?? 0.0) + $days;
                }
            }
        } catch (QueryException $e) {
            Log::warning('aggregateWorkInputs: repli par-employé — absences en échec', [
                'company_id' => $run->company_id,
                'error' => $e->getMessage(),
            ]);
        }

        // Préseed zéro pour TOUS les employés actifs : quand le batch a réussi,
        // le chemin par-employé n'est plus sollicité (seul un échec du batch
        // déclenche le repli, et alors les tableaux restent vides → null).
        foreach ($employees as $employee) {
            $attendance[$employee->id] ??= ['distinct_days' => 0, 'overtime_hours' => 0.0];
            $leave[$employee->id] ??= ['paid_leave_days' => 0.0, 'unpaid_leave_days' => 0.0];
        }

        return [$attendance, $leave];
    }

    private function sumApprovedLeaveDays(PayrollRun $run, Employee $employee, bool $paid): float
    {
        $absences = Absence::query()
            ->where('company_id', $run->company_id)
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $run->period_end)
            ->whereDate('end_date', '>=', $run->period_start)
            ->whereHas('absenceType', function (Builder $q) use ($paid): void {
                $q->where('is_paid', $paid);
            })
            ->get(['start_date', 'end_date', 'days_count']);

        $periodStart = $run->period_start->copy()->startOfDay();
        $periodEnd = $run->period_end->copy()->startOfDay();
        $total = 0.0;

        foreach ($absences as $absence) {
            // Issue #2672 (QA 2026-08-15) — clipping sur la période : une
            // absence chevauchante (ex. 25 janv. → 5 févr.) était comptée en
            // TOTALITÉ dans les runs de janvier ET février (double déduction
            // dans le prorata). On ne compte que l'intersection avec la
            // période, au prorata du days_count stocké.
            if ($absence->end_date === null) {
                continue;
            }

            $overlapStart = $absence->start_date->copy()->max($periodStart);
            $overlapEnd = $absence->end_date->copy()->min($periodEnd);

            if ($overlapEnd->lt($overlapStart)) {
                continue;
            }

            $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;
            $totalSpanDays = $absence->start_date->diffInDays($absence->end_date) + 1;

            $total += $totalSpanDays > 0
                ? (float) $absence->days_count * ($overlapDays / $totalSpanDays)
                : (float) $absence->days_count;
        }

        return $total;
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

    private function computeComponentAmount(SalaryComponent $component, float $baseSalary, float $grossSalary): float
    {
        return match ($component->calculation_type) {
            'fixed' => round((float) $component->amount, 2),
            'percentage_of_base' => round($baseSalary * ((float) $component->percentage / 100), 2),
            'percentage_of_gross' => round($grossSalary * ((float) $component->percentage / 100), 2),
            default => 0.0,
        };
    }

    /**
     * Issue #2117 — parts fiscales du salarié pour la RICF. Lit
     * `employees.family_parts` (décimal, demi-points) ; défaut moteur 1
     * part (célibataire sans enfant à charge → réduction nulle).
     */
    private function familyPartsOf(Employee $employee): float
    {
        $parts = $employee->getAttribute('family_parts');

        return is_numeric($parts) ? (float) $parts : 1.0;
    }
}
