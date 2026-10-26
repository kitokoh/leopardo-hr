<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Exceptions\DsnConformityException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Exports\SgkAphbExportService;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7934 — APHB (e-bildirge, Turquie) : structure et refus des lignes non
 * declarables.
 */
class SgkAphbExportServiceTest extends TestCase
{
    use CreatesMvpSchema;

    /** TCKN mathematiquement valide (cle de controle calculee). */
    private const VALID_TCKN = '10000000146';

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

    public function test_build_produces_aphb_document(): void
    {
        $run = $this->makeRun();

        $xml = (new SgkAphbExportService)->build($run);

        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml), 'Le XML APHB doit etre bien forme.');
        $this->assertSame('AylikPrimVeHizmetBelgesi', $dom->documentElement?->nodeName);

        $this->assertStringContainsString(self::VALID_TCKN, $xml, 'TCKN du sigortali');
        $this->assertStringContainsString('<Ad>Mehmet</Ad>', $xml);
        $this->assertStringContainsString('<Soyad>Yilmaz</Soyad>', $xml);

        // Periode de declaration.
        $this->assertStringContainsString('<DonemYil>2026</DonemYil>', $xml);
        $this->assertStringContainsString('<DonemAy>9</DonemAy>', $xml);

        // Jours de prime : 20 travailles + 2 conges payes + 1 ferie = 23.
        $this->assertStringContainsString('<PrimOdemeGunSayisi>23</PrimOdemeGunSayisi>', $xml);

        // Code de loi 5510 (regime general).
        $this->assertStringContainsString('05510', $xml);
    }

    public function test_assiette_is_capped_at_sgk_ceiling(): void
    {
        // Brut tres au-dessus du plafond SGK (7,5 x salaire minimum).
        $run = $this->makeRun(gross: 500_000.00);

        $xml = (new SgkAphbExportService)->build($run);

        $plafond = number_format(SgkAphbExportService::PLAFOND_SGK_MENSUEL, 2, '.', '');
        $this->assertStringContainsString('<Kazanc>'.$plafond.'</Kazanc>', $xml, 'L assiette doit etre plafonnee');
        $this->assertStringNotContainsString('500000.00', $xml);
    }

    public function test_rejects_missing_tckn(): void
    {
        $run = $this->makeRun(tckn: null);

        $this->expectException(DsnConformityException::class);
        $this->expectExceptionMessageMatches('/TCKN absent/');

        (new SgkAphbExportService)->build($run);
    }

    public function test_rejects_invalid_tckn_checksum(): void
    {
        // 11 chiffres, mais cles de controle fausses.
        $run = $this->makeRun(tckn: '12345678901');

        $this->expectException(DsnConformityException::class);
        $this->expectExceptionMessageMatches('/TCKN invalide/');

        (new SgkAphbExportService)->build($run);
    }

    public function test_rejects_zero_declared_days(): void
    {
        $run = $this->makeRun(daysWorked: 0.0, paidLeave: 0.0, holidays: 0.0, workingDays: 0.0);

        $this->expectException(DsnConformityException::class);
        $this->expectExceptionMessageMatches('/aucun jour de prime/');

        (new SgkAphbExportService)->build($run);
    }

    public function test_falls_back_to_working_days_without_attendance(): void
    {
        // Aucune donnee de presence : on retombe sur les jours ouvres du
        // bulletin plutot que de declarer 0.
        $run = $this->makeRun(daysWorked: 0.0, paidLeave: 0.0, holidays: 0.0, workingDays: 22.0);

        $xml = (new SgkAphbExportService)->build($run);

        $this->assertStringContainsString('<PrimOdemeGunSayisi>22</PrimOdemeGunSayisi>', $xml);
    }

    private function makeRun(
        ?string $tckn = self::VALID_TCKN,
        float $gross = 60_000.00,
        float $daysWorked = 20.0,
        float $paidLeave = 2.0,
        float $holidays = 1.0,
        float $workingDays = 22.0,
    ): PayrollRun {
        $company = Company::factory()->create(['country' => 'TR']);

        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Mehmet',
            'last_name' => 'Yilmaz',
            'matricule' => 'TR-001',
            'status' => 'active',
            'national_id' => $tckn,
        ]);

        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => 'TR',
            'status' => 'validated',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'total_gross' => $gross,
            'total_net' => $gross * 0.7,
            'employee_count' => 1,
        ]);

        PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'gross_salary' => $gross,
            'total_deductions' => $gross * 0.3,
            'net_salary' => $gross * 0.7,
            'employer_contributions' => $gross * 0.2,
            'working_days' => $workingDays,
            'actual_days_worked' => $daysWorked,
            'paid_leave_days' => $paidLeave,
            'public_holiday_days' => $holidays,
            'status' => 'validated',
        ]);

        return $run->fresh() ?? $run;
    }
}
