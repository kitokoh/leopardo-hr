<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface as CountryRulesContract;
use App\Modules\Payroll\Domain\Exceptions\UnsupportedCountryRulesException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryComponent;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * #5591 (slice 2) — calcul des VALEURS d'un bulletin de paie, extrait du
 * god-object PayrollCalculator.
 *
 * Le bulletin (PayrollCalculator::calculateSlip / calculateRegularizationRun)
 * délègue l'ASSEMBLAGE des montants (brut, déductions, net, coût employeur,
 * lignes) à ce service : comportement STRICTEMENT identique (copie), les 448
 * golden tests multi-pays servent de filet.
 *
 * La façade publique PayrollCalculator (computeWorkedDays, computeProratedBase,
 * computeOvertimePay, computeSickLeaveAllowance, computeLeaveIndemnity,
 * computeNetBreakdown, slabTaxBreakdown) reste l'API stable des appelants
 * externes (simulateurs, tests) — délégation simple vers ce service.
 */
class PaySlipValueCalculator
{
    /** Jours ouvrés standards mensuels (DZ) — docs/payroll/DZ_COMPLIANCE.md §5. */
    public const STANDARD_WORKING_DAYS = 22;

    /** Heures mensuelles de référence (base / 173,33 h). */
    public const MONTHLY_HOURS = 173.33;

    private CountryRulesResolver $resolver;

    /** #5591 — agrégats des entrées de travail (instance partagée avec PayrollCalculator). */
    private PayrollWorkInputAggregator $workInputAggregator;

    /**
     * @param  iterable<CountryRulesInterface>  $countryRules  règles custom (tests) ; vide → résolveur par défaut
     */
    public function __construct(
        iterable $countryRules = [],
        private readonly ?PublicHolidayService $publicHolidayService = null,
        ?PayrollWorkInputAggregator $workInputAggregator = null,
    ) {
        $rules = is_array($countryRules) ? $countryRules : iterator_to_array($countryRules);
        $this->resolver = new CountryRulesResolver($rules);
        // #5591 : agrégats des entrées de travail délégués au service dédié
        // (jamais null — repli direct pour les instanciations hors conteneur).
        $this->workInputAggregator = $workInputAggregator ?? new PayrollWorkInputAggregator;
    }

    /**
     * Résout les règles de paie d'un pays via le résolveur unique (#1868).
     *
     * @throws UnsupportedCountryRulesException si le pays n'est pas enregistré
     */
    private function rulesFor(string $countryCode): CountryRulesContract
    {
        return $this->resolver->resolve($countryCode);
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
    public function computeSlipValues(
        PayrollRun $run,
        Employee $employee,
        SalaryStructure $structure,
        CountryRulesContract $rules,
        ?array $attendanceAgg = null,
        ?array $leaveAgg = null
    ): array {
        $baseSalary = $structure->base_salary;
        $worked = $this->computeWorkedDays($run, $employee, $attendanceAgg);
        $inputs = $this->workInputAggregator->collectWorkInputs($run, $employee, $attendanceAgg, $leaveAgg);

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
            return $this->rulesFor($countryCode)->weeklyRestDays();
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

        $gross = $slips->sum(
            fn (PaySlip $slip): float => (float) $slip->gross_salary
        );

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

        $acquired = $row?->getAttribute('acquired');
        $days = is_numeric($acquired) ? (float) $acquired : 0.0;

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

    public function computeComponentAmount(SalaryComponent $component, float $baseSalary, float $grossSalary): float
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
