<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\AbsenceType;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\PayrollRun;
use App\Models\PaySlip;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PrivacyControllerTest extends TestCase
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

    public function test_employee_can_export_own_personal_data_bundle(): void
    {
        [$company, $employee] = $this->privacyActor([
            'preferred_name' => 'Leo',
            'biometric_face_enabled' => true,
            'biometric_consent_at' => now()->subDay(),
        ]);
        $otherEmployee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'colleague@example.test',
        ]);

        AttendanceLog::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
            'check_in' => now()->subHours(8),
            'check_out' => now(),
            'method' => 'mobile',
            'status' => 'completed',
            'hours_worked' => 8,
        ]);
        AttendanceLog::create([
            'company_id' => $company->id,
            'employee_id' => $otherEmployee->id,
            'date' => now()->toDateString(),
            'method' => 'mobile',
            'status' => 'completed',
        ]);

        $absenceType = AbsenceType::factory()->create([
            'company_id' => $company->id,
        ]);
        Absence::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'days_count' => 1,
            'status' => 'pending',
        ]);
        ExpenseClaim::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'title' => 'Taxi',
            'total_amount' => 1200,
            'currency' => 'DZD',
            'status' => 'submitted',
        ]);
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'validated',
        ]);
        PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'validated',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/privacy/export');

        $response->assertOk()
            ->assertJsonPath('data.employee.id', $employee->id)
            ->assertJsonPath('data.employee.preferred_name', 'Leo')
            ->assertJsonPath('data.employee.biometric_face_enabled', true)
            ->assertJsonPath('data.activity_summary.attendance_logs_count', 1)
            ->assertJsonPath('data.activity_summary.absence_requests_count', 1)
            ->assertJsonPath('data.activity_summary.pay_slips_count', 1)
            ->assertJsonPath('data.activity_summary.expense_claims_count', 1)
            ->assertJsonMissing(['email' => 'colleague@example.test']);
    }

    public function test_employee_can_submit_deletion_request_without_destroying_account(): void
    {
        [$company, $employee] = $this->privacyActor();

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/privacy/deletion-request', [
            'reason' => 'Je veux fermer mon compte.',
        ]);

        $response->assertAccepted()
            ->assertJsonPath('data.type', 'deletion')
            ->assertJsonPath('data.status', 'received');

        $this->assertDatabaseHas('privacy_requests', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'deletion',
            'status' => 'received',
        ]);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'email' => $employee->email,
        ]);
    }

    public function test_employee_can_withdraw_biometric_consent(): void
    {
        [, $employee] = $this->privacyActor([
            'biometric_face_enabled' => true,
            'biometric_fingerprint_enabled' => true,
            'biometric_face_reference_path' => 'biometrics/faces/ref.jpg',
            'biometric_fingerprint_reference_path' => 'device:finger-1',
            'biometric_consent_at' => now()->subWeek(),
        ]);

        Sanctum::actingAs($employee);

        $response = $this->patchJson('/api/v1/privacy/biometric-consent', [
            'consented' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.biometric_face_enabled', false)
            ->assertJsonPath('data.biometric_fingerprint_enabled', false)
            ->assertJsonPath('data.biometric_consent_at', null);

        $employee->refresh();
        $this->assertFalse($employee->biometric_face_enabled);
        $this->assertFalse($employee->biometric_fingerprint_enabled);
        $this->assertNull($employee->biometric_face_reference_path);
        $this->assertNull($employee->biometric_fingerprint_reference_path);

        $this->assertDatabaseHas('privacy_requests', [
            'employee_id' => $employee->id,
            'type' => 'biometric_consent',
            'status' => 'completed',
        ]);
    }

    public function test_employee_can_record_biometric_consent_without_enabling_templates(): void
    {
        [, $employee] = $this->privacyActor([
            'biometric_face_enabled' => false,
            'biometric_fingerprint_enabled' => false,
            'biometric_consent_at' => null,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->patchJson('/api/v1/privacy/biometric-consent', [
            'consented' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.biometric_face_enabled', false)
            ->assertJsonPath('data.biometric_fingerprint_enabled', false);

        $employee->refresh();
        $this->assertNotNull($employee->biometric_consent_at);
        $this->assertFalse($employee->biometric_face_enabled);
        $this->assertFalse($employee->biometric_fingerprint_enabled);
    }

    /**
     * @param  array<string, mixed>  $employeeAttributes
     * @return array{0: Company, 1: Employee}
     */
    private function privacyActor(array $employeeAttributes = []): array
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            ...$employeeAttributes,
        ]);

        return [$company, $employee];
    }
}
