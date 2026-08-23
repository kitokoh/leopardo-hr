<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PublicHoliday;
use App\Modules\Payroll\Domain\Models\SalaryComponent;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\IslamicCalendarService;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Payroll\Infrastructure\Services\PublicHolidayService;
use Illuminate\Support\Facades\Cache;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5241 — complétion du moteur paie DZ selon l'audit légal
 * (spec `.specify/features/payroll-dz-100/spec.md` §2, wave W1).
 *
 * Écarts verrouillés ici (chaque valeur CALCULÉE À LA MAIN, jamais reprise
 * du code — méthodologie F-03, docs/payroll/DZ_COMPLIANCE.md) :
 *  - E1 : plafond d'assiette CNAS « aucun » — confirmé sources 2026 + golden ;
 *  - E3 : primes exonérées IRG (SalaryComponent.is_taxable=false) — exclues
 *    de l'assiette IRG, incluses dans l'assiette CNAS ;
 *  - E4 : 13ᵉ mois DZ — non statutaire (convention collective/usage),
 *    `thirteenthMonthMandatory()` = false verrouillé ; le mécanisme
 *    générique reste fonctionnel quand la règle pays le demande ;
 *  - E5 : maladie / arrêt de travail — politique d'indemnisation DZ sourcée
 *    (CNAS : IJ 50 % J1-15 puis 100 % J16+, carence 3 j, max 180 j) +
 *    PayrollCalculator::computeSickLeaveAllowance() ;
 *  - cas particulier : jour férié payé (férié = jour ouvré rémunéré,
 *    workingDaysBetween + pointage sur les jours restants).
 *
 * Partie A : règles pures, SANS base de données (F-13).
 * Partie B : bulletins complets via calculateRun() (RefreshTenantDatabase).
 */
class GoldenDzEngineCompletionTest extends TestCase
{
    use RefreshTenantDatabase;

    private function rules(): AlgeriaPayrollRules
    {
        return new AlgeriaPayrollRules;
    }

    private function calculator(): PayrollCalculator
    {
        return new PayrollCalculator;
    }

    // ─────────────────────────────────────────────────────────────────────
    // E1 — plafond d'assiette CNAS
    // ─────────────────────────────────────────────────────────────────────

    public function test_golden_dz_cnas_has_no_statutory_cap(): void
    {
        // E1 : « aucun plafond » confirmé par sources 2026 (l'Algérie
        // n'applique pas de plafond de cotisation sur les branches
        // principales — contrairement MA/TN) + validation expert 2026-08-08
        // du cœur CNAS 9 %/26 % (DZ_COMPLIANCE.md §2).
        $employee = null;
        $employer = null;
        foreach ($this->rules()->socialContributions() as $contribution) {
            if ($contribution['code'] === 'CNAS_EMP') {
                $employee = $contribution;
            }
            if ($contribution['code'] === 'CNAS_PAT') {
                $employer = $contribution;
            }
        }

        $this->assertNotNull($employee);
        $this->assertNotNull($employer);
        $this->assertNull($employee['cap']);
        $this->assertNull($employer['cap']);
        $this->assertSame(9.0, $employee['rate']);
        $this->assertSame(26.0, $employer['rate']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // E4 — 13ᵉ mois DZ : non statutaire, mécanisme générique verrouillé
    // ─────────────────────────────────────────────────────────────────────

    public function test_golden_dz_thirteenth_month_not_statutory(): void
    {
        // E4 : loi 90-11 n'impose pas de 13ᵉ mois → convention collective /
        // usage. Le moteur ne doit PAS injecter de ligne automatique.
        $this->assertFalse($this->rules()->thirteenthMonthMandatory());
        $this->assertSame('fully_taxable', $this->rules()->thirteenthMonthTaxTreatment());
    }

    public function test_golden_dz_december_run_has_no_auto_thirteenth_month_line(): void
    {
        $company = $this->makeCompany();
        $structure = $this->makeStructure($company, 60000.0);
        $this->makeEmployee($company, $structure->id);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-12-01',
            'period_end' => '2026-12-31',
            'country_code' => 'DZ',
            'status' => 'draft',
        ]);

        $this->calculator()->calculateRun($run);

        $slip = $run->paySlips()->first();
        $this->assertNotNull($slip);
        $this->assertSame(60000.0, (float) $slip->gross_salary);
        $this->assertNull($slip->lines->firstWhere('name', '13ème mois'));
    }

    public function test_golden_dz_thirteenth_month_generic_mechanism_still_works_when_rule_opted_in(): void
    {
        // Le mécanisme générique ZONE-INFRA #1820 reste fonctionnel : si une
        // règle DZ (ou un pays) déclare thirteenthMonthMandatory() = true,
        // le run de décembre inclut la ligne « 13ème mois » imposable.
        $dzWithThirteenthMonth = new class extends AlgeriaPayrollRules
        {
            public function thirteenthMonthMandatory(): bool
            {
                return true;
            }
        };

        $company = $this->makeCompany();
        $structure = $this->makeStructure($company, 60000.0);
        $this->makeEmployee($company, $structure->id);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-12-01',
            'period_end' => '2026-12-31',
            'country_code' => 'DZ',
            'status' => 'draft',
        ]);

        (new PayrollCalculator([$dzWithThirteenthMonth]))->calculateRun($run);

        $slip = $run->paySlips()->first();
        $this->assertNotNull($slip);
        $this->assertSame(120000.0, (float) $slip->gross_salary);

        $line = $slip->lines->firstWhere('name', '13ème mois');
        $this->assertNotNull($line);
        $this->assertSame(60000.0, (float) $line->amount);
    }

    // ─────────────────────────────────────────────────────────────────────
    // E5 — maladie / arrêt de travail : politique + indemnités journalières
    // ─────────────────────────────────────────────────────────────────────

    public function test_golden_dz_sick_leave_policy_values(): void
    {
        // Sources CNAS (cnas.dz) + loi 90-11 — confidenceLevel 'pilot'.
        $policy = $this->rules()->sickLeavePolicy();

        $this->assertSame(3, $policy['waiting_days']);
        $this->assertSame(180, $policy['max_paid_days']);
        $this->assertSame(0, $policy['employer_maintenance_days']);
        $this->assertSame(
            [
                ['from_day' => 1, 'to_day' => 15, 'rate' => 0.50],
                ['from_day' => 16, 'to_day' => null, 'rate' => 1.00],
            ],
            $policy['daily_allowance_rates']
        );
    }

    public function test_golden_dz_sick_leave_allowance_within_careance(): void
    {
        // 2 jours d'arrêt ≤ carence (3 j) → aucune IJ.
        $this->assertSame(
            0.0,
            $this->calculator()->computeSickLeaveAllowance(2000.0, 2.0, $this->rules())
        );
    }

    public function test_golden_dz_sick_leave_allowance_10_days(): void
    {
        // Salaire journalier de référence 2 000 DZD (base 60 000 / 30 j),
        // arrêt 10 j → carence 3 j → 7 j indemnisés :
        //   jours 4-10 d'arrêt, tranche J1-15 @ 50 % → 7 × 2 000 × 0,50 = 7 000
        $this->assertSame(
            7000.0,
            $this->calculator()->computeSickLeaveAllowance(2000.0, 10.0, $this->rules())
        );
    }

    public function test_golden_dz_sick_leave_allowance_20_days_crosses_rate_tier(): void
    {
        // Arrêt 20 j → carence 3 j → 17 j indemnisés :
        //   jours d'arrêt 4-15 (12 j) @ 50 %  = 12 × 2 000 × 0,50 = 12 000
        //   jours d'arrêt 16-20 (5 j) @ 100 % =  5 × 2 000 × 1,00 = 10 000
        //   total = 22 000
        $this->assertSame(
            22000.0,
            $this->calculator()->computeSickLeaveAllowance(2000.0, 20.0, $this->rules())
        );
    }

    public function test_golden_dz_sick_leave_allowance_capped_at_max_paid_days(): void
    {
        // Arrêt 200 j → plafonné à max_paid_days (180 j) → carence 3 →
        // 177 j au départ, plafonnés à 180 jours INDEMNISABLES (le plafond
        // porte sur l'indemnisation, pas sur le brut d'arrêt) :
        //   jours d'arrêt 4-15 (12 j) @ 50 %  = 12 × 2 000 × 0,50 = 12 000
        //   jours d'arrêt 16-183 (168 j) @ 100 % = 168 × 2 000 × 1,00 = 336 000
        //   total = 348 000
        $this->assertSame(
            348000.0,
            $this->calculator()->computeSickLeaveAllowance(2000.0, 200.0, $this->rules())
        );
    }

    public function test_golden_default_sick_leave_policy_is_inert(): void
    {
        // Pays sans politique maladie (défaut AbstractCountryRules) → aucune
        // indemnisation calculée, quel que soit le nombre de jours.
        $calculator = new PayrollCalculator;
        $rules = $calculator->getRules('FR');

        $this->assertSame(
            [
                'waiting_days' => 0,
                'daily_allowance_rates' => [],
                'max_paid_days' => 0,
                'employer_maintenance_days' => 0,
            ],
            $rules->sickLeavePolicy()
        );
        $this->assertSame(0.0, $calculator->computeSickLeaveAllowance(2000.0, 20.0, $rules));
    }

    // ─────────────────────────────────────────────────────────────────────
    // E3 — prime exonérée (is_taxable=false) : bulletin complet
    // ─────────────────────────────────────────────────────────────────────

    public function test_golden_dz_run_prime_non_imposable_10000(): void
    {
        // Calcul manuel (DZ_COMPLIANCE.md §1-§2 + §8) — brut = base 60 000
        // + prime NON imposable 10 000 (is_taxable=false) :
        //   prime exonérée : AJOUTÉE au brut (assiette CNAS complète) mais
        //     EXCLUE de l'assiette IRG (E3)
        //   CNAS salariale = 70 000 × 9 % = 6 300 → assiette IRG =
        //     70 000 − 10 000 − 6 300 = 53 700
        //   IRG(53 700) : 4 600 + 13 700 × 27 % = 8 299 → annuel 99 588
        //     → abattement plafonné 18 000 → IRG mensuel (99 588 − 18 000)/12
        //     = 6 799
        //   déductions = 6 300 + 6 799 = 13 099 → net = 70 000 − 13 099 = 56 901
        //   patronale = 18 200 → coût employeur = 88 200
        $company = $this->makeCompany();
        $structure = $this->makeStructure($company, 60000.0);
        SalaryComponent::create([
            'company_id' => $company->id,
            'salary_structure_id' => $structure->id,
            'name' => 'Prime de transport (exonérée)',
            'code' => 'PRIME_TRANSPORT_5241',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'amount' => 10000.0,
            'percentage' => 0.0,
            'is_taxable' => false,
            'is_recurring' => true,
            'order' => 1,
            'active' => true,
        ]);
        $this->makeEmployee($company, $structure->id);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'draft',
        ]);

        $this->calculator()->calculateRun($run);

        $slip = $run->paySlips()->first();
        $this->assertNotNull($slip);

        $this->assertSame(70000.0, (float) $slip->gross_salary);
        $this->assertSame(13099.0, (float) $slip->total_deductions);
        $this->assertSame(56901.0, (float) $slip->net_salary);
        $this->assertSame(18200.0, (float) $slip->employer_contributions);
        $this->assertSame(88200.0, (float) $slip->total_cost);

        $primeLine = $slip->lines->firstWhere('name', 'Prime de transport (exonérée)');
        $this->assertNotNull($primeLine);
        $this->assertSame(10000.0, (float) $primeLine->amount);

        // L'assiette visible de la ligne IRG = brut − prime exonérée − CNAS.
        $irgLine = $slip->lines->firstWhere('name', 'Impot sur le revenu');
        $this->assertNotNull($irgLine);
        $this->assertSame(53700.0, (float) $irgLine->base_amount);
        $this->assertSame(6799.0, (float) $irgLine->amount);

        // La cotisation salariale reste calculée sur le brut COMPLET
        // (assiette CNAS inchangée — position documentée, à confirmer expert).
        $cnasLine = $slip->lines->firstWhere('name', 'Cotisations salariales');
        $this->assertNotNull($cnasLine);
        $this->assertSame(6300.0, (float) $cnasLine->amount);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Cas particulier — jour férié payé (férié = jour ouvré rémunéré)
    // ─────────────────────────────────────────────────────────────────────

    public function test_golden_dz_public_holiday_is_paid_workday(): void
    {
        // Juillet 2026 DZ : 22 jours ouvrés (repos vendredi/samedi).
        // Férié d'entreprise le 15/07 (mercredi, jour ouvré) → 21 jours
        // ouvrés ; l'employé pointe les 21 jours → salaire COMPLET (60 000) :
        // le férié est payé (workingDaysBetween l'exclut des jours travaillés
        // à fournir, pas de la rémunération).
        $company = $this->makeCompany();
        $structure = $this->makeStructure($company, 60000.0);
        $employee = $this->makeEmployee($company, $structure->id);

        PublicHoliday::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'name' => 'Férié test #5241',
            'date' => '2026-07-15',
            'year' => 2026,
            'is_recurring' => false,
            'holiday_type' => 'custom',
        ]);

        $service = new PublicHolidayService(Cache::store(), new IslamicCalendarService(Cache::store()));
        $calculator = new PayrollCalculator([], $service);

        // Jours ouvrés DZ de juillet 2026 (repos vendredi/samedi) = 22 jours ;
        // retrait du 15/07 (férié) → 21 jours à pointer pour un salaire complet.
        $workingDays = ['01', '02', '05', '06', '07', '08', '09', '12', '13', '14', '16', '19', '20', '21', '22', '23', '26', '27', '28', '29', '30'];
        $this->assertCount(21, $workingDays);

        foreach ($workingDays as $day) {
            AttendanceLog::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'date' => "2026-07-{$day}",
                'status' => 'ontime',
                'overtime_hours' => 0.0,
            ]);
        }

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'draft',
        ]);

        $calculator->calculateRun($run);

        $slip = $run->paySlips()->first();
        $this->assertNotNull($slip);

        $this->assertSame(21.0, (float) $slip->working_days);
        $this->assertSame(21.0, (float) $slip->actual_days_worked);
        // Salaire complet malgré le férié : 60 000 × 21/21 = 60 000.
        $this->assertSame(60000.0, (float) $slip->gross_salary);
        // CNAS 5 400 + IRG(54 600) = 7 042 → déductions 12 442 → net 47 558
        // (calcul identique au golden F-03, DZ_COMPLIANCE §1bis).
        $this->assertSame(12442.0, (float) $slip->total_deductions);
        $this->assertSame(47558.0, (float) $slip->net_salary);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers (Partie B — DB)
    // ─────────────────────────────────────────────────────────────────────

    private function makeCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        return $company;
    }

    private function makeStructure(Company $company, float $base): SalaryStructure
    {
        /** @var SalaryStructure $structure */
        $structure = SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille #5241 — '.$base,
            'base_salary' => $base,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        return $structure;
    }

    private function makeEmployee(Company $company, ?int $structureId = null): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'contract_start' => '2026-01-01',
            'salary_structure_id' => $structureId,
        ]);

        return $employee;
    }
}
