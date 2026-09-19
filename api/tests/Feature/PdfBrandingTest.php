<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Infrastructure\Services\PaySlipPdfGenerator;
use App\Support\PdfBranding;
use Illuminate\Support\Facades\Storage;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7713 — image de marque du tenant dans les PDF.
 *
 * Le logo uploadé via PATCH /company/branding (`metadata.branding.logo_path`
 * + `logo_disk`) doit apparaître en en-tête des PDF (chemin fichier LOCAL
 * résolu via Storage — dompdf ne suit pas d'URL http), et la couleur primaire
 * teinte les en-têtes de tableau. Robustesse absolue : branding absent,
 * fichier manquant ou couleur invalide → rendu identique à l'historique,
 * jamais d'exception.
 */
class PdfBrandingTest extends TestCase
{
    use RefreshTenantDatabase;

    /** PNG 1×1 valide (binaire minimal) pour matérialiser le logo sur disque. */
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    public function test_receipt_pdf_renders_tenant_logo_and_primary_color_when_branding_is_set(): void
    {
        Storage::fake('public');
        $logoPath = 'company-branding/test/logo.png';
        Storage::disk('public')->put($logoPath, (string) base64_decode(self::TINY_PNG_BASE64, true));

        $company = $this->companyWithBranding([
            'logo_path' => $logoPath,
            'logo_disk' => 'public',
            'primary_color' => '#7C3AED',
        ]);

        $expectedLogo = Storage::disk('public')->path($logoPath);
        $this->assertSame($expectedLogo, PdfBranding::logoPath($company));
        $this->assertSame('#7C3AED', PdfBranding::primaryColor($company, ''));

        $html = $this->renderReceipt($company);

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('logo.png', $html);
        $this->assertStringContainsString('#7C3AED', $html);
    }

    public function test_receipt_pdf_is_unchanged_without_branding(): void
    {
        $company = Company::factory()->create(['language' => 'fr']);

        $this->assertNull(PdfBranding::logoPath($company));
        $this->assertSame('', PdfBranding::primaryColor($company, ''));

        $html = $this->renderReceipt($company);

        $this->assertStringNotContainsString('<img', $html);
        // L'en-tête de tableau historique reste intact.
        $this->assertStringContainsString('#f5f5f5', $html);
    }

    public function test_branding_falls_back_silently_on_missing_file_or_invalid_color(): void
    {
        Storage::fake('public');

        $company = $this->companyWithBranding([
            'logo_path' => 'company-branding/test/missing.png',
            'logo_disk' => 'public',
            'primary_color' => 'not-a-color',
        ]);

        $this->assertNull(PdfBranding::logoPath($company));
        $this->assertSame('', PdfBranding::primaryColor($company, ''));

        $html = $this->renderReceipt($company);

        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_payslip_pdf_generates_without_error_with_branding_set(): void
    {
        Storage::fake('public');
        $logoPath = 'company-branding/test/logo.png';
        Storage::disk('public')->put($logoPath, (string) base64_decode(self::TINY_PNG_BASE64, true));

        $company = $this->companyWithBranding([
            'logo_path' => $logoPath,
            'logo_disk' => 'public',
            'primary_color' => '#7C3AED',
        ]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'preferred_language' => 'fr',
        ]);

        [, $slip] = $this->payrollSlip($company, $employee);

        $binary = app(PaySlipPdfGenerator::class)->generate($slip);

        $this->assertNotEmpty($binary);
        $this->assertStringStartsWith('%PDF', $binary);
    }

    public function test_payslip_pdf_generates_without_error_when_logo_file_is_missing(): void
    {
        Storage::fake('public');

        $company = $this->companyWithBranding([
            'logo_path' => 'company-branding/test/missing.png',
            'logo_disk' => 'public',
            'primary_color' => '#7C3AED',
        ]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'preferred_language' => 'fr',
        ]);

        [, $slip] = $this->payrollSlip($company, $employee);

        $binary = app(PaySlipPdfGenerator::class)->generate($slip);

        $this->assertNotEmpty($binary);
    }

    /** @param array<string, mixed> $branding */
    private function companyWithBranding(array $branding): Company
    {
        return Company::factory()->create([
            'language' => 'fr',
            'metadata' => ['branding' => $branding],
        ]);
    }

    private function renderReceipt(Company $company): string
    {
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'preferred_language' => 'fr',
        ]);

        return view('pdf.receipt', [
            'company' => $company,
            'employee' => $employee,
            'estimate' => [
                'period' => ['from' => '2026-05-01', 'to' => '2026-05-31'],
                'breakdown' => [
                    [
                        'date' => '2026-05-04',
                        'hours' => 8.0,
                        'overtime_hours' => 0.0,
                        'base_gain' => 4500.0,
                        'overtime_gain' => 0.0,
                        'total' => 4500.0,
                    ],
                ],
                'totals' => ['gross' => 4500.0, 'deductions' => 810.0, 'net' => 3690.0],
                'currency' => 'DZD',
            ],
        ])->render();
    }

    /**
     * Même fixture que PaySlipPdfLocaleTest::payrollSlip.
     *
     * @return array{0: PayrollRun, 1: PaySlip}
     */
    private function payrollSlip(Company $company, Employee $employee): array
    {
        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'validated',
            'employee_count' => 1,
            'total_gross' => 120000,
            'total_deductions' => 22000,
            'total_net' => 98000,
        ]);

        $slip = PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 120000,
            'total_deductions' => 22000,
            'net_salary' => 98000,
            'employer_contributions' => 31200,
            'total_cost' => 151200,
            'working_days' => 22,
            'actual_days_worked' => 22,
            'overtime_hours' => 0,
            'status' => 'validated',
        ]);

        PaySlipLine::query()->create([
            'pay_slip_id' => $slip->id,
            'name' => 'Salaire de base',
            'type' => 'earning',
            'base_amount' => 120000,
            'rate' => 1,
            'amount' => 120000,
            'order' => 1,
        ]);

        return [$run, $slip];
    }
}
