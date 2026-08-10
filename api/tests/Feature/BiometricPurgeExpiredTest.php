<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\BiometricEnrollmentRequest;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Spec S-1 (#1661) — Biométrie : politique de rétention + purge automatique.
 * La commande `biometric:purge-expired` nullifie les références de templates
 * dont la durée de conservation est dépassée (24 mois après fin de contrat,
 * ou après consentement sans fin de contrat), tenant par tenant, tracée dans
 * `audit_logs`, avec dry-run.
 */
class BiometricPurgeExpiredTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_purges_expired_templates_after_contract_end(): void
    {
        $company = Company::factory()->create();

        $expired = Employee::factory()->create([
            'company_id' => $company->id,
            'contract_end' => now()->subMonths(30)->format('Y-m-d'),
            'biometric_face_enabled' => true,
            'biometric_face_reference_path' => 'biometrics/faces/expired.jpg',
            'biometric_fingerprint_enabled' => true,
            'biometric_fingerprint_reference_path' => 'biometrics/fp/expired.jpg',
            'biometric_consent_at' => now()->subMonths(30),
        ]);

        $this->artisan('biometric:purge-expired', ['--company' => $company->id])
            ->expectsOutputToContain('employe(s) avec templates biometriques expire(s)')
            ->assertExitCode(0);

        $fresh = $expired->fresh();
        $this->assertNull($fresh->biometric_face_reference_path);
        $this->assertNull($fresh->biometric_fingerprint_reference_path);
        $this->assertFalse((bool) $fresh->biometric_face_enabled);
        $this->assertFalse((bool) $fresh->biometric_fingerprint_enabled);

        // Opération tracée dans audit_logs.
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'biometric_templates_purged',
        ]);
    }

    public function test_keeps_templates_of_active_employees(): void
    {
        $company = Company::factory()->create();

        // Employé encore en poste : contrat non terminé, consentement ancien → conservé.
        $active = Employee::factory()->create([
            'company_id' => $company->id,
            'contract_end' => now()->addMonths(6)->format('Y-m-d'),
            'biometric_face_enabled' => true,
            'biometric_face_reference_path' => 'biometrics/faces/active.jpg',
            'biometric_consent_at' => now()->subMonths(30),
        ]);

        // Employé sans contrat ni consentement → conservé (aucune référence expirable).
        $noData = Employee::factory()->create([
            'company_id' => $company->id,
            'contract_end' => null,
            'biometric_consent_at' => null,
        ]);

        $this->artisan('biometric:purge-expired', ['--company' => $company->id])
            ->assertExitCode(0);

        $this->assertNotNull($active->fresh()->biometric_face_reference_path);
        $this->assertDatabaseMissing('audit_logs', [
            'company_id' => $company->id,
            'action' => 'biometric_templates_purged',
        ]);
        $this->assertNotNull($noData->fresh()->biometric_face_reference_path ?? 'no-ref');
    }

    public function test_purges_consent_expired_when_no_contract_end(): void
    {
        $company = Company::factory()->create();

        $expired = Employee::factory()->create([
            'company_id' => $company->id,
            'contract_end' => null,
            'biometric_consent_at' => now()->subMonths(30),
            'biometric_fingerprint_enabled' => true,
            'biometric_fingerprint_reference_path' => 'biometrics/fp/consent-old.jpg',
        ]);

        $this->artisan('biometric:purge-expired', ['--company' => $company->id])
            ->assertExitCode(0);

        $this->assertNull($expired->fresh()->biometric_fingerprint_reference_path);
    }

    public function test_is_idempotent(): void
    {
        $company = Company::factory()->create();

        Employee::factory()->create([
            'company_id' => $company->id,
            'contract_end' => now()->subMonths(36)->format('Y-m-d'),
            'biometric_face_reference_path' => 'biometrics/faces/x.jpg',
        ]);

        $this->artisan('biometric:purge-expired', ['--company' => $company->id])
            ->assertExitCode(0);
        $firstAudits = AuditLog::query()
            ->where('company_id', $company->id)
            ->where('action', 'biometric_templates_purged')
            ->count();
        $this->assertSame(1, $firstAudits);

        // Second run : plus rien à purger, aucune nouvelle trace.
        $this->artisan('biometric:purge-expired', ['--company' => $company->id])
            ->assertExitCode(0);
        $secondAudits = AuditLog::query()
            ->where('company_id', $company->id)
            ->where('action', 'biometric_templates_purged')
            ->count();
        $this->assertSame(1, $secondAudits);
    }

    public function test_dry_run_does_not_write(): void
    {
        $company = Company::factory()->create();

        $expired = Employee::factory()->create([
            'company_id' => $company->id,
            'contract_end' => now()->subMonths(30)->format('Y-m-d'),
            'biometric_face_reference_path' => 'biometrics/faces/dry.jpg',
        ]);

        $this->artisan('biometric:purge-expired', [
            '--company' => $company->id,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertNotNull($expired->fresh()->biometric_face_reference_path);
        $this->assertDatabaseMissing('audit_logs', [
            'company_id' => $company->id,
            'action' => 'biometric_templates_purged',
        ]);
    }

    public function test_purges_enrollment_request_reference_paths(): void
    {
        $company = Company::factory()->create();

        $expired = Employee::factory()->create([
            'company_id' => $company->id,
            'contract_end' => now()->subMonths(30)->format('Y-m-d'),
            'biometric_face_reference_path' => 'biometrics/faces/emp.jpg',
        ]);

        BiometricEnrollmentRequest::create([
            'company_id' => $company->id,
            'employee_id' => $expired->id,
            'status' => 'approved',
            'requested_face_reference_path' => 'biometrics/requests/face-1.jpg',
            'requested_fingerprint_reference_path' => 'biometrics/requests/fp-1.jpg',
        ]);

        $this->artisan('biometric:purge-expired', ['--company' => $company->id])
            ->assertExitCode(0);

        $request = BiometricEnrollmentRequest::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $expired->id)
            ->firstOrFail();
        $this->assertNull($request->requested_face_reference_path);
        $this->assertNull($request->requested_fingerprint_reference_path);
    }

    public function test_unknown_company_returns_failure(): void
    {
        $this->artisan('biometric:purge-expired', ['--company' => '00000000-0000-0000-0000-000000000000'])
            ->assertExitCode(1);
    }
}
