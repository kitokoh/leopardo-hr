<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\BiometricEnrollmentRequest;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Illuminate\Support\Facades\Storage;
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
            'middle_name' => 'Karim',
            'personal_email' => 'perso@exemple.dz',
            'recovery_email' => 'recovery@exemple.dz',
            'personal_phone' => '0550 12 34 56',
            'date_of_birth' => '1990-05-15',
            'place_of_birth' => 'Alger',
            'nationality' => 'DZ',
            'national_id' => '123456789012345',
            'iban' => 'DZ0000000000000000000000',
            'bank_account' => '0000000000',
            'zkteco_id' => 'ZK-42',
            'biometric_face_enabled' => true,
            'biometric_face_reference_path' => 'biometrics/faces/1.jpg',
            'photo_path' => 'photos/employe-1.jpg',
        ]);

        // Demande d'enrôlement biométrique à purger (chemins de référence + notes).
        BiometricEnrollmentRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'status' => 'approved',
            'requested_face_reference_path' => 'biometrics/requests/face-1.jpg',
            'requested_fingerprint_reference_path' => 'biometrics/requests/fp-1.jpg',
            'employee_note' => 'Note employé',
            'manager_note' => 'Note manager',
        ]);
        Storage::fake('local');
        Storage::disk('local')->put('photos/employe-1.jpg', 'fake-photo');

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
        $cmd = $this->artisan('gdpr:anonymize-employee', ['employee' => $employee->id, '--force' => true]);
        $cmd->assertSuccessful();

        $employee->refresh();

        // PII effacées/anonymisées (jeu complet : noms, emails, téléphones, adresse,
        // naissance, nationalité, NID, IBAN, compte bancaire, zkteco_id, biométrie, photo).
        $this->assertSame('Anonymisé', $employee->first_name);
        $this->assertSame('anonyme-'.$employee->id.'@anonyme.local', $employee->email);
        $this->assertNull($employee->middle_name);
        $this->assertNull($employee->personal_email);
        $this->assertNull($employee->recovery_email);
        $this->assertNull($employee->personal_phone);
        $this->assertNull($employee->getRawOriginal('date_of_birth'));
        $this->assertNull($employee->getRawOriginal('place_of_birth'));
        $this->assertNull($employee->nationality);
        $this->assertNull($employee->getRawOriginal('national_id'));
        $this->assertNull($employee->getRawOriginal('iban'));
        $this->assertNull($employee->getRawOriginal('bank_account'));
        $this->assertNull($employee->zkteco_id);
        $this->assertFalse($employee->biometric_face_enabled);
        $this->assertNull($employee->biometric_face_reference_path);
        $this->assertNull($employee->getRawOriginal('photo_path'));
        $this->assertSame('archived', $employee->status);

        // Fichier photo supprimé du disque.
        Storage::disk('local')->assertMissing('photos/employe-1.jpg');

        // Demandes d'enrôlement biométrique purgées.
        $this->assertSame(1, BiometricEnrollmentRequest::query()->where('employee_id', $employee->id)->count());
        $this->assertNull(BiometricEnrollmentRequest::query()->where('employee_id', $employee->id)->value('requested_face_reference_path'));
        $this->assertNull(BiometricEnrollmentRequest::query()->where('employee_id', $employee->id)->value('employee_note'));

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

    public function test_anonymize_dry_run_writes_nothing(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'dry.run@leopardo.test',
            'first_name' => 'Sara',
        ]);

        /** @var \Illuminate\Testing\PendingCommand $cmd */
        $cmd = $this->artisan('gdpr:anonymize-employee', ['employee' => $employee->id, '--dry-run' => true]);
        $cmd->assertSuccessful();

        $employee->refresh();
        $this->assertSame('Sara', $employee->first_name);
        $this->assertSame('dry.run@leopardo.test', $employee->email);
        $this->assertSame(0, AuditLog::where('action', 'gdpr_employee_anonymized')->where('auditable_id', $employee->id)->count());
    }

    public function test_anonymize_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'bis@leopardo.test',
            'first_name' => 'Yacine',
        ]);

        /** @var \Illuminate\Testing\PendingCommand $cmd */
        $cmd = $this->artisan('gdpr:anonymize-employee', ['employee' => $employee->id, '--force' => true]);
        $cmd->assertSuccessful();
        /** @var \Illuminate\Testing\PendingCommand $cmd */
        $cmd = $this->artisan('gdpr:anonymize-employee', ['employee' => $employee->id, '--force' => true]);
        $cmd->assertSuccessful();

        // Une seule entrée d'audit, pas de doublon.
        $this->assertSame(1, AuditLog::where('action', 'gdpr_employee_anonymized')->where('auditable_id', $employee->id)->count());
    }

    public function test_anonymize_with_company_scope(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'tenant@leopardo.test',
            'first_name' => 'Lina',
        ]);

        /** @var \Illuminate\Testing\PendingCommand $cmd */
        $cmd = $this->artisan('gdpr:anonymize-employee', [
            'employee' => $employee->id,
            '--company' => $company->id,
            '--force' => true,
        ]);
        $cmd->assertSuccessful();

        $employee->refresh();
        $this->assertSame('Anonymisé', $employee->first_name);
    }
}
