<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Exports\DsnExportService;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #5438 — Export DSN (S21.G00) : structure XML minimale, valeurs des blocs,
 * isolation par run. (Validation URSSAF complète hors périmètre — pilot.)
 */
class DsnExportServiceTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_build_produces_well_formed_s21_xml(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['timezone' => 'Europe/Paris']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Jeanne',
            'last_name' => 'Martin',
            'matricule' => 'EMP-FR-001',
            'status' => 'active',
        ]);

        /** @var PayrollRun $run */
        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => 'FR',
            'status' => 'validated',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'total_gross' => 3000.00,
            'total_net' => 2202.81,
            'employee_count' => 1,
        ]);

        PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'gross_salary' => 3000.00,
            'total_deductions' => 644.41,
            'net_salary' => 2202.81,
            'employer_contributions' => 1026.60,
            'status' => 'validated',
        ]);

        $xml = (new DsnExportService)->build($run);

        // Bien formé + namespace DSN.
        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml), 'Le XML DSN doit être bien formé.');
        $root = $dom->documentElement;
        if ($root === null) {
            $this->fail('DSN XML : élément racine manquant.');
        }
        $this->assertSame('urn:fr:dsi:dsn:v1', $root->namespaceURI);

        // Blocs attendus.
        $this->assertSame(1, $dom->getElementsByTagNameNS('urn:fr:dsi:dsn:v1', 'EnTete')->length);
        $this->assertSame(1, $dom->getElementsByTagNameNS('urn:fr:dsi:dsn:v1', 'Declaration')->length);

        $blocs = [];
        foreach ($dom->getElementsByTagNameNS('urn:fr:dsi:dsn:v1', 'Bloc') as $bloc) {
            $blocs[] = $bloc->getAttribute('Id');
        }
        $this->assertContains('S21.G00.01', $blocs, 'Bloc Individu présent');
        $this->assertContains('S21.G00.02', $blocs, 'Bloc Contrat présent');
        $this->assertContains('S21.G00.06', $blocs, 'Bloc Rémunération présent');
        $this->assertContains('S21.G00.11', $blocs, 'Bloc Cotisation présent');

        // Valeurs : individu + rémunération.
        $this->assertStringContainsString('Jeanne', $xml);
        $this->assertStringContainsString('Martin', $xml);
        $this->assertStringContainsString('3000.00', $xml);
        $this->assertStringContainsString('2202.81', $xml);
        $this->assertStringContainsString('1026.60', $xml);
    }
}
