<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\BiometricEnrollmentRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * S-1 (#1661) — Purge automatique des templates biométriques expirés.
 *
 * Règle : rétention = max(fin de contrat, consentement) + 24 mois (config
 * security.biometric.retention_months). Couvre : purgé / non-purgé /
 * dry-run / idempotent / tenant-scoped / demandes d'enrôlement / traçage
 * audit.
 */
class BiometricPurgeExpiredCommandTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function makeCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        return $company;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeExpiredEmployee(Company $company, array $overrides = []): Employee
    {
        $facePath = 'biometrics/faces/'.$company->id.'/face.jpg';
        $fingerprintPath = 'biometrics/faces/'.$company->id.'/fp.jpg';

        /** @var Employee $employee */
        $employee = Employee::factory()->create(array_merge([
            'company_id' => $company->id,
            'biometric_face_enabled' => true,
            'biometric_fingerprint_enabled' => true,
            'biometric_face_reference_path' => $facePath,
            'biometric_fingerprint_reference_path' => $fingerprintPath,
            'biometric_consent_at' => now()->subMonths(30),
        ], $overrides));

        Storage::disk('local')->put($facePath, 'fake-face');
        Storage::disk('local')->put($fingerprintPath, 'fake-fp');

        return $employee;
    }

    public function test_purges_expired_employee_templates_and_traces_audit(): void
    {
        $company = $this->makeCompany();
        $employee = $this->makeExpiredEmployee($company);

        $exitCode = Artisan::call('biometric:purge-expired');
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Terminé', Artisan::output());

        $employee->refresh();
        $this->assertNull($employee->biometric_face_reference_path);
        $this->assertNull($employee->biometric_fingerprint_reference_path);
        $this->assertFalse($employee->biometric_face_enabled);
        $this->assertFalse($employee->biometric_fingerprint_enabled);
        $this->assertFalse(Storage::disk('local')->exists('biometrics/faces/'.$company->id.'/face.jpg'));

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'biometric_templates_purged',
        ]);
        $audit = AuditLog::where('action', 'biometric_templates_purged')->first();
        $this->assertNotNull($audit);
        $this->assertSame(1, $audit->metadata['employees_purged']);
    }

    public function test_keeps_recent_templates(): void
    {
        $company = $this->makeCompany();
        $employee = $this->makeExpiredEmployee($company, [
            'biometric_consent_at' => now()->subMonths(1),
        ]);

        $this->assertSame(0, Artisan::call('biometric:purge-expired'));

        $employee->refresh();
        $this->assertNotNull($employee->biometric_face_reference_path);
        $this->assertTrue($employee->biometric_face_enabled);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'biometric_templates_purged']);
    }

    public function test_contract_end_delays_retention(): void
    {
        $company = $this->makeCompany();

        // Contrat terminé il y a 12 mois seulement → référence = fin de contrat,
        // +24 mois → pas encore expiré malgré un consentement de 36 mois.
        $active = $this->makeExpiredEmployee($company, [
            'contract_end' => now()->subMonths(12)->toDateString(),
        ]);

        // Contrat terminé il y a 30 mois → expiré.
        $expired = $this->makeExpiredEmployee($company, [
            'contract_end' => now()->subMonths(30)->toDateString(),
        ]);

        $this->assertSame(0, Artisan::call('biometric:purge-expired'));

        $active->refresh();
        $expired->refresh();
        $this->assertNotNull($active->biometric_face_reference_path);
        $this->assertNull($expired->biometric_face_reference_path);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $company = $this->makeCompany();
        $employee = $this->makeExpiredEmployee($company);

        $exitCode = Artisan::call('biometric:purge-expired', ['--dry-run' => true]);
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('DRY-RUN', Artisan::output());

        $employee->refresh();
        $this->assertNotNull($employee->biometric_face_reference_path);
        $this->assertTrue($employee->biometric_face_enabled);
        $this->assertTrue(Storage::disk('local')->exists('biometrics/faces/'.$company->id.'/face.jpg'));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'biometric_templates_purged']);
    }

    public function test_is_idempotent(): void
    {
        $company = $this->makeCompany();
        $this->makeExpiredEmployee($company);

        $this->assertSame(0, Artisan::call('biometric:purge-expired'));
        $this->assertSame(0, Artisan::call('biometric:purge-expired'));

        $this->assertSame(1, AuditLog::where('action', 'biometric_templates_purged')->count());
    }

    public function test_company_scoped(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $this->makeExpiredEmployee($companyA);
        $employeeB = $this->makeExpiredEmployee($companyB);

        $this->assertSame(0, Artisan::call('biometric:purge-expired', ['--company' => (string) $companyA->id]));

        $employeeB->refresh();
        $this->assertNotNull($employeeB->biometric_face_reference_path);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $companyA->id, 'action' => 'biometric_templates_purged']);
        $this->assertDatabaseMissing('audit_logs', ['company_id' => $companyB->id, 'action' => 'biometric_templates_purged']);
    }

    public function test_purges_expired_enrollment_requests(): void
    {
        $company = $this->makeCompany();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        /** @var BiometricEnrollmentRequest $request */
        $request = BiometricEnrollmentRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'status' => 'pending',
            'requested_face_reference_path' => 'biometrics/requests/face.jpg',
            'requested_fingerprint_reference_path' => 'biometrics/requests/fp.jpg',
            'submitted_at' => now()->subMonths(30),
        ]);
        Storage::disk('local')->put('biometrics/requests/face.jpg', 'fake-face');

        $this->assertSame(0, Artisan::call('biometric:purge-expired'));

        $request->refresh();
        $this->assertNull($request->requested_face_reference_path);
        $this->assertNull($request->requested_fingerprint_reference_path);
        $this->assertFalse(Storage::disk('local')->exists('biometrics/requests/face.jpg'));
    }
}
