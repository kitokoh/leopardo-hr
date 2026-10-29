<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\PaySlipLegalMentions;
use App\Modules\Payroll\Infrastructure\Services\PaySlipPdfGenerator;
use Tests\RefreshTenantDatabase;
use Tests\Support\PdfTextExtractor;
use Tests\TestCase;

/**
 * Issue #9045 (lot BC-07 P1) — référentiels de mentions de bulletin
 * FR/TR/CA/CM/CI/SN exposés dans le générateur PDF.
 *
 * Critères d'acceptation de l'issue :
 *   1. un bulletin généré par pays contient toutes ses mentions obligatoires ;
 *   2. aucune mention française codée en dur dans un bulletin non-FR.
 */
class PaySlipLegalMentionsByCountryTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * Identifiants employeur attendus par pays (clé `company.metadata`).
     *
     * @return array<string, array<string, string>>
     */
    private function employerMetadata(): array
    {
        return [
            'FR' => [
                'legal_siret' => '12345678900012',
                'legal_ape' => '6201Z',
                'legal_urssaf' => '117000000000000123',
                'legal_convention_collective' => 'SYNTEC',
            ],
            'TR' => [
                'legal_sgk_workplace' => '1234567-01',
                'legal_vergi_no' => '1234567890',
                'legal_ticaret_sicil' => '987654-5',
            ],
            'CA' => [
                'legal_bn' => '123456789RC0001',
                'legal_gst_hst' => '123456789RT0001',
                'legal_tvq' => '1223456789TQ0001',
            ],
            'CM' => [
                'legal_niu' => 'M1234567890123',
                'legal_rccm' => 'RC/DLA/2020/B/1234',
                'legal_cnps_employer' => '9876543210',
            ],
            'CI' => [
                'legal_ncc' => '1234567A',
                'legal_rccm' => 'CI-ABJ-2020-B-12345',
                'legal_cnps_employer' => '1122334455',
            ],
            'SN' => [
                'legal_ninea' => '1234567890123',
                'legal_rccm' => 'SN-DKR-2020-B-1234',
                'legal_ipres_employer' => '5566778899',
            ],
        ];
    }

    /**
     * Identifiants salarié attendus par pays (attribut `Employee`).
     *
     * @return array<string, array<string, string>>
     */
    private function employeeAttributes(): array
    {
        return [
            'FR' => ['matricule' => 'FR-0001', 'national_id' => '185017512345678'],
            'TR' => ['matricule' => 'TR-0001', 'national_id' => '10000000146'],
            'CA' => ['matricule' => 'CA-0001', 'national_id' => '123456789'],
            'CM' => ['matricule' => 'CM-0001', 'cnps_matricule' => 'CNPS-CM-0001'],
            'CI' => ['matricule' => 'CI-0001', 'cnss_ci_matricule' => 'CNSS-CI-0001'],
            'SN' => ['matricule' => 'SN-0001', 'ipres_matricule' => 'IPRES-SN-0001'],
        ];
    }

    private function makeSlip(string $country, array $metadata, array $employeeAttributes): PaySlip
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => $country,
            'metadata' => $metadata,
        ]);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'preferred_language' => 'fr',
            ...$employeeAttributes,
        ]);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => $country,
            'status' => 'validated',
        ]);

        /** @var PaySlip $slip */
        $slip = PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'gross_salary' => 500000,
            'total_deductions' => 100000,
            'net_salary' => 400000,
            'status' => 'validated',
        ]);

        return $slip;
    }

    /**
     * Critère 1 — chaque pays couvert imprime ses mentions obligatoires.
     */
    public function test_each_covered_country_renders_its_own_legal_mentions(): void
    {
        $employer = $this->employerMetadata();
        $employees = $this->employeeAttributes();

        foreach (PaySlipLegalMentions::coveredCountries() as $country) {
            if (! isset($employer[$country], $employees[$country])) {
                continue; // DZ est couvert par PaySlipDzMentionsTest.
            }

            $slip = $this->makeSlip($country, $employer[$country], $employees[$country]);
            $pdf = app(PaySlipPdfGenerator::class)->generate($slip);

            $this->assertNotSame('', $pdf, "PDF vide pour {$country}");
            $text = PdfTextExtractor::text($pdf);
            // dompdf scinde les longues valeurs en plusieurs runs de texte :
            // on compare sans espaces pour ne pas dépendre du découpage.
            $flat = (string) preg_replace('/\s+/', '', $text);

            // Les identifiants employeur du pays sont présents.
            foreach ($employer[$country] as $value) {
                $this->assertStringContainsString(
                    (string) preg_replace('/\s+/', '', $value),
                    $flat,
                    "Identifiant employeur {$value} absent du bulletin {$country}"
                );
            }

            // Les identifiants salarié du pays sont présents.
            foreach ($employees[$country] as $value) {
                $this->assertStringContainsString(
                    (string) preg_replace('/\s+/', '', $value),
                    $flat,
                    "Identifiant salarié {$value} absent du bulletin {$country}"
                );
            }
        }
    }

    /**
     * Critère 2 — aucune mention française codée en dur dans un bulletin
     * non-FR. On vérifie la phrase française du gabarit FR sur les pays dont
     * la langue juridique n'est pas le français.
     */
    public function test_non_french_payslips_do_not_carry_the_french_notice(): void
    {
        $frenchNotice = PaySlipLegalMentions::notice('FR');
        $this->assertNotNull($frenchNotice);

        foreach (['TR', 'CA'] as $country) {
            $slip = $this->makeSlip(
                $country,
                $this->employerMetadata()[$country],
                $this->employeeAttributes()[$country],
            );

            $pdf = app(PaySlipPdfGenerator::class)->generate($slip);
            $text = PdfTextExtractor::text($pdf);

            $this->assertStringNotContainsString(
                'articles L3243-1 et R3243-1 du Code du travail',
                $text,
                "Le bulletin {$country} porte la mention légale française"
            );
            // La mention du pays est bien celle de sa juridiction.
            $this->assertStringContainsString(
                (string) PaySlipLegalMentions::notice($country),
                $text,
                "Le bulletin {$country} n'imprime pas sa propre mention légale"
            );
        }
    }

    /**
     * Règle 2 — aucun fallback pays silencieux : un pays hors référentiel
     * n'obtient aucune mention inventée.
     */
    public function test_unknown_country_gets_no_invented_notice(): void
    {
        $this->assertNull(PaySlipLegalMentions::notice('ZZ'));
        $this->assertNull(PaySlipLegalMentions::notice(null));
        $this->assertSame([], PaySlipLegalMentions::employerIdentifiers(null, 'ZZ'));
    }

    /**
     * Les six pays de l'issue sont couverts, plus le gabarit DZ historique.
     */
    public function test_reference_covers_the_six_issue_countries(): void
    {
        $covered = PaySlipLegalMentions::coveredCountries();

        foreach (['FR', 'TR', 'CA', 'CM', 'CI', 'SN'] as $country) {
            $this->assertContains($country, $covered, "Pays {$country} non couvert");
            $this->assertNotNull(PaySlipLegalMentions::notice($country));
        }
    }
}
