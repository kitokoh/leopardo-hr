<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Programme FOCUS — F-18 (#1548) : droit à l'effacement (RGPD / Loi 18-07).
 * L'anonymisation efface les PII mais conserve l'historique de paie
 * (obligation légale de conservation) et trace l'opération.
 */
class GdprAnonymizeEmployeeTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_anonymize_employee_removes_pii_and_keeps_payroll_history(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'pii.employe@leopardo.test',
            'first_name' => 'Mohamed',
            'last_name' => 'Benali',
            'personal_phone' => '0550 12 34 56',
            'date_of_birth' => '1990-05-15',
            'nationality' => 'DZ',
            'biometric_face_enabled' => true,
        ]);

        // Historique de paie à conserver.
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);
        PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'gross_salary' => 60000,
            'net_salary' => 47558,
            'status' => 'validated',
        ]);

        /** @var \Illuminate\Testing\PendingCommand $cmd */
        $cmd = $this->artisan('gdpr:anonymize-employee', ['employee' => $employee->id]);
        $cmd->assertSuccessful();

        $employee->refresh();

        // PII effacées/anonymisées.
        $this->assertSame('Anonymisé', $employee->first_name);
        $this->assertSame('anonyme-'.$employee->id.'@anonyme.local', $employee->email);
        $this->assertNull($employee->personal_phone);
        $this->assertNull($employee->getRawOriginal('date_of_birth'));
        $this->assertNull($employee->nationality);
        $this->assertFalse($employee->biometric_face_enabled);
        $this->assertSame('archived', $employee->status);

        // Historique de paie conservé.
        $this->assertSame(1, PaySlip::query()->where('employee_id', $employee->id)->count());
        $this->assertSame(60000.0, (float) PaySlip::query()->where('employee_id', $employee->id)->value('gross_salary'));

        // Opération tracée.
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'gdpr_employee_anonymized',
            'auditable_id' => $employee->id,
        ]);
        $log = AuditLog::where('action', 'gdpr_employee_anonymized')->where('auditable_id', $employee->id)->first();
        $this->assertNotNull($log);
        $this->assertTrue($log->metadata['payroll_history_kept']);
    }

    public function test_anonymize_unknown_employee_fails(): void
    {
        /** @var \Illuminate\Testing\PendingCommand $cmd */
        $cmd = $this->artisan('gdpr:anonymize-employee', ['employee' => 999999]);
        $cmd->assertFailed();
    }
}
