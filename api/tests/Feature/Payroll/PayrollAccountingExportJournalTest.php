<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Infrastructure\Exports\PayrollAccountingExportService;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\PayrollCountryChartOfAccounts;
use App\Support\CountryDefaults;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5256 — Moteur multi-pays : plan comptable par pays + écritures salariales
 * équilibrées (débit = crédit) produites par
 * PayrollAccountingExportService::journalLines().
 *
 * Méthodologie : chaque montant attendu est CALCULÉ À LA MAIN
 * (docs/payroll/MULTI_PAYS_PLAN_COMPTABLE.md, constitution §III) — une
 * divergence = régression du pont Paie → Comptabilité (#5239).
 */
class PayrollAccountingExportJournalTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_dz_journal_is_balanced_and_uses_pcg_accounts(): void
    {
        // Bulletin calculé à la main : brut 60 000, cotisations salariales
        // 5 000, impôt 3 000, autres déductions 2 000 → net 50 000 ;
        // charges patronales 9 000.
        // Écritures attendues (PCN, §6.3) :
        //   D 641 60 000 · D 645 9 000
        //   C 421 50 000 · C 431 (5 000 + 9 000) · C 4421 3 000 · C 425 2 000
        //   Total débit 69 000 = total crédit 69 000 par bulletin.
        [$run] = $this->runWithSlips('DZ');

        $lines = (new PayrollAccountingExportService)->journalLines($run);

        // 2 bulletins × 6 écritures.
        $this->assertCount(12, $lines);

        $debits = array_sum(array_column($lines, 'debit'));
        $credits = array_sum(array_column($lines, 'credit'));
        $this->assertSame(138000.0, $debits);
        $this->assertSame($debits, $credits);

        // Codes comptes attendus (1 écriture par compte, agrégée par bulletin).
        $accountTotals = [];
        foreach ($lines as $line) {
            $accountTotals[$line['account_code']]['debit'] = ($accountTotals[$line['account_code']]['debit'] ?? 0.0) + $line['debit'];
            $accountTotals[$line['account_code']]['credit'] = ($accountTotals[$line['account_code']]['credit'] ?? 0.0) + $line['credit'];
        }
        $this->assertSame(120000.0, $accountTotals['641']['debit']);
        $this->assertSame(18000.0, $accountTotals['645']['debit']);
        $this->assertSame(100000.0, $accountTotals['421']['credit']);
        $this->assertSame(28000.0, $accountTotals['431']['credit']); // 5 000 ×2 + 9 000 ×2
        $this->assertSame(6000.0, $accountTotals['4421']['credit']);
        $this->assertSame(4000.0, $accountTotals['425']['credit']);

        // Équilibre par bulletin ET débit/crédit exclusifs.
        foreach ([$run->paySlips()->orderBy('id')->first(), $run->paySlips()->orderByDesc('id')->first()] as $slip) {
            $slipLines = array_values(array_filter(
                $lines,
                fn (array $line): bool => $line['pay_slip_id'] === (int) $slip->id
            ));
            $this->assertSame(
                array_sum(array_column($slipLines, 'debit')),
                array_sum(array_column($slipLines, 'credit'))
            );
            foreach ($slipLines as $line) {
                $this->assertTrue(
                    ($line['debit'] > 0.0) xor ($line['credit'] > 0.0),
                    'débit OU crédit exclusif (jamais les deux)'
                );
            }
        }
    }

    public function test_journal_lines_carry_traceability_metadata(): void
    {
        [$run, $employee] = $this->runWithSlips('DZ');

        $lines = (new PayrollAccountingExportService)->journalLines($run);

        $first = $lines[0];
        $this->assertSame('2026-06-30', $first['date']);
        $this->assertSame($run->company_id, $first['company_id']);
        $this->assertSame((int) $run->id, $first['payroll_run_id']);
        $this->assertSame((int) $employee->id, $first['employee_id']);
        $this->assertSame('PAYROLL-RUN-'.$run->id, $first['reference']);
        $this->assertSame('641', $first['account_code']);
        $this->assertSame('Salaires et appointements', $first['account_label']);
    }

    public function test_journal_ignores_non_validated_slips(): void
    {
        [$run] = $this->runWithSlips('DZ', 'calculated');

        $lines = (new PayrollAccountingExportService)->journalLines($run);

        $this->assertSame([], $lines);
    }

    public function test_journal_mixes_only_validated_slips(): void
    {
        [$run] = $this->runWithSlips('DZ', 'validated');

        // Ajoute un 3e bulletin non validé (nouvel employé — contrainte
        // unique (payroll_run_id, employee_id)) — il doit être ignoré.
        /** @var Employee $draftEmployee */
        $draftEmployee = Employee::factory()->create([
            'company_id' => $run->company_id,
            'first_name' => 'Paul',
            'last_name' => 'Petit',
            'matricule' => null,
        ]);
        PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
            'employee_id' => $draftEmployee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 99999,
            'total_deductions' => 0,
            'net_salary' => 99999,
            'status' => 'draft',
        ]);

        $lines = (new PayrollAccountingExportService)->journalLines($run);

        $this->assertCount(12, $lines); // 2 validés × 6 — le draft est exclu
    }

    public function test_regularization_negative_slip_stays_balanced(): void
    {
        // Bulletin de régularisation négatif (différentiel) : brut −1 500,
        // cotisations −125, impôt −75, net −1 300, patronales −225.
        [$run] = $this->runWithSlips('DZ', 'validated');

        /** @var PaySlip $slip */
        $slip = $run->paySlips()->first();
        $slip->update([
            'gross_salary' => -1500.0,
            'total_deductions' => -200.0,
            'net_salary' => -1300.0,
            'employer_contributions' => -225.0,
        ]);
        PaySlipLine::query()->where('pay_slip_id', $slip->id)->delete();
        PaySlipLine::create(['pay_slip_id' => $slip->id, 'name' => 'Cotisations salariales', 'type' => 'deduction', 'amount' => -125.0]);
        PaySlipLine::create(['pay_slip_id' => $slip->id, 'name' => 'Impot sur le revenu', 'type' => 'deduction', 'amount' => -75.0]);

        $lines = (new PayrollAccountingExportService)->journalLines($run);

        // Débits négatifs comptés dans la somme : équilibre conservé.
        $this->assertSame(
            array_sum(array_column($lines, 'debit')),
            array_sum(array_column($lines, 'credit'))
        );

        // Le bulletin corrigé : D 641 −1 500 · D 645 −225 · C 421 −1 300 ·
        // C 431 (−125 + −225) · C 4421 −75.
        $slipLines = array_values(array_filter(
            $lines,
            fn (array $line): bool => $line['pay_slip_id'] === (int) $slip->id
        ));
        $this->assertCount(5, $slipLines);
        $this->assertSame(-1725.0, array_sum(array_column($slipLines, 'debit')));
        $this->assertSame(-1725.0, array_sum(array_column($slipLines, 'credit')));
    }

    public function test_senegal_flat_tax_is_booked_to_income_tax_account(): void
    {
        // Sénégal : la taxe de minimum fiscal (TRIMF) est une retenue —
        // elle doit atterrir sur 4421 (impôt), pas sur 425 (autres).
        [$run] = $this->runWithSlips('SN');

        /** @var PaySlip $slip */
        $slip = $run->paySlips()->first();
        PaySlipLine::create([
            'pay_slip_id' => $slip->id,
            'name' => 'Taxe de minimum fiscal',
            'type' => 'deduction',
            'amount' => 500.0,
        ]);
        // total_deductions intègre la TRIMF : 5 000 + 3 000 + 2 000 + 500.
        $slip->update(['total_deductions' => 10500.0, 'net_salary' => 49500.0]);

        $lines = (new PayrollAccountingExportService)->journalLines($run);

        $slipLines = array_values(array_filter(
            $lines,
            fn (array $line): bool => $line['pay_slip_id'] === (int) $slip->id
        ));

        $this->assertSame(3500.0, $this->totalFor($slipLines, '4421', 'credit')); // 3 000 + 500
        $this->assertSame(2000.0, $this->totalFor($slipLines, '425', 'credit'));
        $this->assertSame(
            array_sum(array_column($slipLines, 'debit')),
            array_sum(array_column($slipLines, 'credit'))
        );
    }

    public function test_france_uses_pcg_accounts(): void
    {
        [$run] = $this->runWithSlips('FR');

        $lines = (new PayrollAccountingExportService)->journalLines($run);

        $codes = array_unique(array_column($lines, 'account_code'));
        sort($codes, SORT_STRING);
        $this->assertSame(['421', '425', '431', '4421', '641', '645'], $codes);
        $this->assertJournalBalanced($lines);
    }

    public function test_turkey_uses_thp_accounts(): void
    {
        [$run] = $this->runWithSlips('TR');

        $lines = (new PayrollAccountingExportService)->journalLines($run);

        $codes = array_unique(array_column($lines, 'account_code'));
        sort($codes, SORT_STRING);
        // 770 (salaires + patronales), 335 net, 361 SGK, 360 impôt, 135 avances.
        $this->assertSame(['135', '335', '360', '361', '770'], $codes);
        $this->assertJournalBalanced($lines);
    }

    public function test_united_kingdom_uses_uk_practice_accounts(): void
    {
        [$run] = $this->runWithSlips('GB');

        $lines = (new PayrollAccountingExportService)->journalLines($run);

        $codes = array_unique(array_column($lines, 'account_code'));
        sort($codes, SORT_STRING);
        $this->assertSame(['2210', '2300', '2310', '622'], $codes);
        $this->assertJournalBalanced($lines);
    }

    public function test_united_states_uses_us_practice_accounts(): void
    {
        [$run] = $this->runWithSlips('US');

        $lines = (new PayrollAccountingExportService)->journalLines($run);

        $codes = array_unique(array_column($lines, 'account_code'));
        sort($codes, SORT_STRING);
        $this->assertSame(['1010', '2020', '2030', '2040', '6010', '6040'], $codes);
        $this->assertJournalBalanced($lines);
    }

    public function test_cedeaq_member_derives_syscohada_from_senegal(): void
    {
        $senegal = PayrollCountryChartOfAccounts::forCountry('SN');
        $mali = PayrollCountryChartOfAccounts::forCountry('ML');

        $this->assertNotNull($senegal);
        $this->assertNotNull($mali);
        $this->assertSame('SN', $mali['base_country']);
        $this->assertSame($senegal['accounts'], $mali['accounts']);
    }

    public function test_cemac_member_derives_syscohada_from_cameroon(): void
    {
        $cameroon = PayrollCountryChartOfAccounts::forCountry('CM');
        $gabon = PayrollCountryChartOfAccounts::forCountry('GA');

        $this->assertNotNull($cameroon);
        $this->assertNotNull($gabon);
        $this->assertSame('CM', $gabon['base_country']);
        $this->assertSame($cameroon['accounts'], $gabon['accounts']);
    }

    public function test_every_registered_country_has_a_chart_of_accounts(): void
    {
        // DoD #5256 : « Tous les pays déclarés produisent un export comptable
        // cohérent » — chaque pays du registre officiel a un plan comptable.
        $registered = CountryDefaults::all();
        $charts = PayrollCountryChartOfAccounts::all();

        $this->assertCount(count($registered), $charts);

        $registeredCodes = array_column($registered, 'country');
        $chartCodes = array_column($charts, 'country');
        sort($registeredCodes);
        sort($chartCodes);
        $this->assertSame($registeredCodes, $chartCodes);

        foreach ($charts as $chart) {
            $this->assertCount(6, $chart['accounts']);
            $this->assertContains($chart['confidence_level'], ['production', 'pilot']);
            $this->assertNotSame('', $chart['reference']);
            foreach ($chart['accounts'] as $account) {
                $this->assertNotSame('', $account['code']);
                $this->assertNotSame('', $account['label']);
            }
        }
    }

    public function test_unknown_country_returns_empty_journal(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'country_code' => 'ZZ',
            'status' => 'validated',
        ]);

        $this->assertSame([], (new PayrollAccountingExportService)->journalLines($run));
        $this->assertNull(PayrollCountryChartOfAccounts::forCountry('ZZ'));
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function assertJournalBalanced(array $lines): void
    {
        $this->assertNotEmpty($lines);
        $this->assertSame(
            array_sum(array_column($lines, 'debit')),
            array_sum(array_column($lines, 'credit'))
        );
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function totalFor(array $lines, string $accountCode, string $side): float
    {
        $total = 0.0;
        foreach ($lines as $line) {
            if ($line['account_code'] === $accountCode) {
                $total += (float) $line[$side];
            }
        }

        return $total;
    }

    /**
     * Fixture : un run avec 2 bulletins validés identiques (brut 60 000,
     * cotisations salariales 5 000, impôt 3 000, avance 2 000 → net 50 000,
     * charges patronales 9 000).
     *
     * @return array{0: PayrollRun, 1: Employee}
     */
    private function runWithSlips(string $country = 'DZ', string $status = 'validated'): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => 'DZD']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'matricule' => null,
        ]);

        /** @var Employee $employee2 */
        $employee2 = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Marie',
            'last_name' => 'Martin',
            'matricule' => null,
        ]);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'country_code' => $country,
            'status' => 'locked',
        ]);

        foreach ([$employee, $employee2] as $target) {
            /** @var PaySlip $slip */
            $slip = PaySlip::create([
                'payroll_run_id' => $run->id,
                'company_id' => $run->company_id,
                'employee_id' => $target->id,
                'period_start' => $run->period_start,
                'period_end' => $run->period_end,
                'gross_salary' => 60000,
                'total_deductions' => 10000,
                'net_salary' => 50000,
                'employer_contributions' => 9000,
                'total_cost' => 69000,
                'status' => $status,
            ]);

            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Cotisations salariales',
                'type' => 'deduction',
                'amount' => 5000,
            ]);
            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Impot sur le revenu',
                'type' => 'deduction',
                'amount' => 3000,
            ]);
            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Avance',
                'type' => 'deduction',
                'amount' => 2000,
            ]);
            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => 'Cotisations patronales',
                'type' => 'employer_contribution',
                'amount' => 9000,
            ]);
        }

        return [$run, $employee];
    }
}
