<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Privacy\Domain\Enums\PiiSensitivity;
use App\Modules\HR\Infrastructure\Services\PiiLifecycleService;
use App\Core\Privacy\Infrastructure\Services\PiiRegistry;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\PrivacyRequest;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Illuminate\Support\Facades\Storage;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * MAT-011 (#5869) — Classification PII et cycle de vie (BC-01 PLATFORM).
 *
 * Critères d'acceptation :
 *  - chaque champ sensible possède une politique (catalogue complet) ;
 *  - les droits RGPD (export, anonymisation, suppression, rétention) sont
 *    testés et audités.
 */
class PiiLifecycleTest extends TestCase
{
    use RefreshTenantDatabase;

    private function registry(): PiiRegistry
    {
        return app(PiiRegistry::class);
    }

    private function lifecycle(): PiiLifecycleService
    {
        return app(PiiLifecycleService::class);
    }

    public function test_pii_catalog_is_complete_and_valid(): void
    {
        self::assertSame([], $this->registry()->validateCatalog(), 'le catalogue PII doit être complet et valide');

        // Chaque champ anonymisé par le droit à l'effacement possède une politique.
        $anonymizedFields = [
            'first_name', 'last_name', 'middle_name', 'preferred_name', 'email',
            'personal_email', 'recovery_email', 'personal_phone', 'phone',
            'address_line', 'postal_code', 'date_of_birth', 'place_of_birth',
            'gender', 'nationality', 'marital_status', 'national_id', 'iban',
            'bank_account', 'zkteco_id', 'photo_path', 'emergency_contact_name',
            'emergency_contact_phone', 'biometric_face_reference_path',
            'biometric_fingerprint_reference_path',
        ];

        foreach ($anonymizedFields as $field) {
            self::assertNotNull($this->registry()->policy($field), "politique manquante pour le champ sensible '{$field}'");
        }
    }

    public function test_classify_returns_sensitivity_levels(): void
    {
        self::assertSame(PiiSensitivity::High, $this->lifecycle()->classify('national_id'));
        self::assertSame(PiiSensitivity::High, $this->lifecycle()->classify('iban'));
        self::assertSame(PiiSensitivity::Medium, $this->lifecycle()->classify('email'));
        self::assertSame(PiiSensitivity::Low, $this->lifecycle()->classify('preferred_name'));
        self::assertNull($this->lifecycle()->classify('not_a_pii_field'));
    }

    public function test_export_bundle_contains_employee_and_activity_summary(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Amina',
            'last_name' => 'Said',
            'email' => 'amina.said@leopardo.test',
        ]);

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

        $bundle = $this->lifecycle()->exportBundle($employee);

        self::assertSame($employee->id, $bundle['employee']['id']);
        self::assertSame('Amina', $bundle['employee']['first_name']);
        self::assertSame(1, $bundle['activity_summary']['pay_slips_count']);
        self::assertSame(0, $bundle['activity_summary']['attendance_logs_count']);
        self::assertSame('1.0.0', $bundle['privacy']['catalog_version']);
        self::assertSame('authenticated_employee_self_service', $bundle['privacy']['scope']);
    }

    public function test_anonymize_removes_pii_keeps_payroll_and_audits(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'pii.lifecycle@leopardo.test',
            'first_name' => 'Yacine',
            'last_name' => 'Brahimi',
            'national_id' => '998877665544332',
            'iban' => 'DZ0000000000000000000000',
            'photo_path' => 'photos/lifecycle-1.jpg',
        ]);
        Storage::fake('local');
        Storage::disk('local')->put('photos/lifecycle-1.jpg', 'fake-photo');

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

        $result = $this->lifecycle()->anonymize($employee);

        self::assertTrue($result['anonymized']);
        self::assertGreaterThan(20, $result['fields_changed']);
        self::assertTrue($result['photo_deleted']);

        $employee->refresh();
        self::assertSame('Anonymisé', $employee->first_name);
        self::assertStringContainsString('anonyme-', (string) $employee->email);
        self::assertNull($employee->national_id);
        self::assertNull($employee->iban);
        self::assertNull($employee->photo_path);
        self::assertSame('archived', $employee->status);
        self::assertFalse(Storage::disk('local')->exists('photos/lifecycle-1.jpg'));

        // Historique de paie conservé (obligation légale).
        self::assertSame(1, PaySlip::query()->where('employee_id', $employee->id)->count());

        // Opération auditée.
        self::assertSame(1, AuditLog::query()
            ->where('action', 'gdpr_employee_anonymized')
            ->where('auditable_id', $employee->id)
            ->count());
    }

    public function test_anonymize_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Anonymisé',
            'last_name' => 'Employé '.PHP_INT_MAX,
            'status' => 'archived',
        ]);

        $result = $this->lifecycle()->anonymize($employee);

        self::assertFalse($result['anonymized']);
        self::assertSame(0, $result['fields_changed']);
        self::assertSame(0, AuditLog::query()->where('action', 'gdpr_employee_anonymized')->count());
    }

    public function test_dry_run_does_not_write(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'dry.run@leopardo.test',
            'first_name' => 'Dry',
            'last_name' => 'Run',
        ]);

        $result = $this->lifecycle()->anonymize($employee, dryRun: true);

        self::assertFalse($result['anonymized']);
        self::assertGreaterThan(20, $result['fields_changed']);

        $employee->refresh();
        self::assertSame('Dry', $employee->first_name);
        self::assertSame('dry.run@leopardo.test', $employee->email);
        self::assertSame(0, AuditLog::query()->where('action', 'gdpr_employee_anonymized')->count());
    }

    public function test_deletion_request_is_recorded(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $request = $this->lifecycle()->requestDeletion(
            $employee,
            'Départ volontaire',
            ['ip' => '127.0.0.1', 'user_agent' => 'phpunit'],
        );

        self::assertInstanceOf(PrivacyRequest::class, $request);
        self::assertSame('deletion', $request->type);
        self::assertSame('received', $request->status);
        self::assertSame('Départ volontaire', $request->requested_payload['reason']);
        self::assertFalse($request->requested_payload['destructive_action']);
    }

    public function test_retention_schedule_is_derived_from_catalog(): void
    {
        $schedule = $this->lifecycle()->retentionSchedule();

        // Données bancaires/paie : 120 mois (DZ 10 ans) · biométrie : 24 mois.
        self::assertSame(120, $schedule['payroll']);
        self::assertSame(24, $schedule['attendance']);
    }
}
