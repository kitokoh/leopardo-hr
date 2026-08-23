<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5266 — heures supplémentaires DZ : règles légales + intégration paie.
 *
 * Verrouille l'arbitrage E2 de la spec `payroll-dz-100` :
 *   - loi 90-11 art. 32 : majoration « qui ne peut en aucun cas être
 *     inférieure à 50 % du salaire horaire normal » → palier unique × 1,5,
 *     sans barème conventionnel 25 %/50 % (sous le minimum légal) ;
 *   - loi 90-11 art. 26 : durée légale hebdomadaire = 40 h ;
 *   - loi 90-11 art. 36 : travail un jour de repos légal → repos
 *     compensateur d'égale durée (règle documentée, suivi RH hors paie).
 *
 * Méthode : valeurs en dur calculées à la main — référence
 * docs/payroll/DZ_COMPLIANCE.md §5 (précision complète #2685 : taux
 * horaire 60 000 / 173,33 = 346,160503… non arrondi avant majoration).
 *
 * DoD #5266 : le run de paie DZ intègre les HS SANS intervention manuelle
 * (pipeline F-20 : AttendanceLog.overtime_hours → collectWorkInputs →
 * computeOvertimePay($rules) → ligne « Heures supplémentaires » du slip).
 */
class GoldenDzOvertimeRulesTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_golden_dz_overtime_legal_threshold_and_tiers(): void
    {
        $rules = new AlgeriaPayrollRules;

        // Art. 26 loi 90-11 : durée légale hebdomadaire 40 h.
        $this->assertSame(40.0, $rules->overtimeThresholdWeeklyHours());

        // Art. 32 loi 90-11 : majoration ≥ 50 % — palier unique illimité.
        $this->assertSame([
            ['up_to_hours' => null, 'multiplier' => 1.5],
        ], $rules->overtimeRateTiers());
    }

    public static function overtimePayProvider(): array
    {
        return [
            'zéro heure' => [60000.0, 0.0, 0.0],
            // #2685 : 60000/173,33 = 346,160503… conservé non arrondi
            // jusqu'au résultat final (round à 2).
            '5 h à 50 %' => [60000.0, 5.0, 2596.20],
            '10 h à 50 %' => [60000.0, 10.0, 5192.41],
            '15 h à 50 %' => [60000.0, 15.0, 7788.61],
            '20 h à 50 %' => [60000.0, 20.0, 10384.82],
        ];
    }

    #[DataProvider('overtimePayProvider')]
    public function test_golden_dz_overtime_pay_with_country_tiers(float $base, float $hours, float $expected): void
    {
        $this->assertSame($expected, (new PayrollCalculator)->computeOvertimePay($base, $hours, 10, new AlgeriaPayrollRules));
    }

    public function test_golden_dz_overtime_fallback_without_rules_is_unchanged(): void
    {
        // Rétro-compatibilité : sans règles pays, la mécanique générique
        // F-05 (25 % jusqu'au seuil conventionnel puis 50 %) est conservée
        // pour les appels hors run (dashboard, estimation, tiers-lieux).
        $this->assertSame(4327.01, (new PayrollCalculator)->computeOvertimePay(60000.0, 10.0));
        $this->assertSame(6923.21, (new PayrollCalculator)->computeOvertimePay(60000.0, 15.0));
    }

    public function test_golden_dz_run_integrates_overtime_without_manual_intervention(): void
    {
        // DoD #5266 — un run de paie DZ intègre les HS sans intervention
        // manuelle. Calcul manuel (DZ_COMPLIANCE.md §5 + #5266) :
        //   HS = 15 × (60 000/173,33) × 1,50 = 7 788,61
        //   brut = 60 000 + 7 788,61 = 67 788,61
        //   CNAS salariale = 67 788,61 × 9 % = 6 100,97 → assiette 61 687,64
        //   IRG(61 687,64) : 4 600 + 21 687,64×27 % = 10 455,66
        //     → annuel 125 467,92 → abattement plafonné 18 000 → IRG 8 955,66
        //   net = 67 788,61 − 6 100,97 − 8 955,66 = 52 731,98
        //   patronale = 17 625,04 → coût employeur = 85 413,65
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var SalaryStructure $structure */
        $structure = SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille Golden #5266 — 60 000',
            'base_salary' => 60000.0,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'contract_start' => '2026-01-01',
            'salary_structure_id' => $structure->id,
        ]);

        // Jours ouvrés DZ de juillet 2026 (repos hebdo vendredi/samedi —
        // AlgeriaPayrollRules::weeklyRestDays [5, 6]) : 22 jours distincts.
        $workingDays = ['01', '02', '05', '06', '07', '08', '09', '12', '13', '14', '15', '16', '19', '20', '21', '22', '23', '26', '27', '28', '29', '30'];
        foreach ($workingDays as $day) {
            AttendanceLog::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'date' => "2026-07-{$day}",
                'status' => 'ontime',
                'overtime_hours' => $day === '08' ? 15.0 : 0.0,
            ]);
        }

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'draft',
        ]);

        (new PayrollCalculator())->calculateRun($run);

        $slip = $run->paySlips()->first();
        $this->assertNotNull($slip);

        $this->assertSame(15.0, (float) $slip->overtime_hours);
        $this->assertTrue((bool) $slip->has_attendance_data);

        $this->assertSame(67788.61, (float) $slip->gross_salary);
        $this->assertSame(52731.98, (float) $slip->net_salary);
        $this->assertSame(17625.04, (float) $slip->employer_contributions);
        $this->assertSame(85413.65, (float) $slip->total_cost);

        $hsLine = $slip->lines->where('name', 'Heures supplémentaires')->first();
        $this->assertNotNull($hsLine);
        $this->assertSame(15.0, (float) $hsLine->base_amount);
        $this->assertSame(7788.61, (float) $hsLine->amount);
    }
}
