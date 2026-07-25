<?php

namespace Tests\Feature\Absences;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-MOB-006 — Absence supporting document ("pieces").
 *
 * The manager decision view already needs "qui quoi combien pourquoi et
 * pieces" for absence requests; this covers the upload-at-creation and
 * secure-download halves of that requirement.
 */
class AbsenceProofTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function makeCompany(): Company
    {
        return Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);
    }

    private function makeSchedule(Company $company): Schedule
    {
        return Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Day',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);
    }

    private function makeAbsenceType(Company $company): AbsenceType
    {
        return AbsenceType::query()->create([
            'company_id' => $company->id,
            'name' => 'Congé maladie',
            'code' => 'CM',
            'is_paid' => true,
            'deducts_leave' => false,
            'requires_proof' => true,
        ]);
    }

    public function test_employee_can_attach_a_proof_document_when_creating_an_absence(): void
    {
        $company = $this->makeCompany();
        $schedule = $this->makeSchedule($company);
        $absenceType = $this->makeAbsenceType($company);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $file = UploadedFile::fake()->create('medical-note.pdf', 200, 'application/pdf');

        $response = $this->postJson('/api/v1/absences', [
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'reason' => 'Maladie',
            'proof' => $file,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.has_proof', true);

        $absence = Absence::query()->firstOrFail();
        $this->assertNotNull($absence->proof_path);
        Storage::disk('local')->assertExists($absence->proof_path);
    }

    public function test_absence_created_without_proof_has_no_proof(): void
    {
        $company = $this->makeCompany();
        $schedule = $this->makeSchedule($company);
        $absenceType = $this->makeAbsenceType($company);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/absences', [
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'reason' => 'Maladie',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.has_proof', false);
        $response->assertJsonPath('data.proof_path', null);
    }

    public function test_owning_employee_can_download_their_proof(): void
    {
        $company = $this->makeCompany();
        $schedule = $this->makeSchedule($company);
        $absenceType = $this->makeAbsenceType($company);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $path = UploadedFile::fake()->create('note.pdf', 100)->store('absences/proofs/'.$company->id, 'local');

        $absence = Absence::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'days_count' => 3,
            'status' => 'pending',
            'proof_path' => $path,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->get("/api/v1/absences/{$absence->id}/proof");

        $response->assertStatus(200);
    }

    public function test_manager_can_download_a_team_member_proof(): void
    {
        $company = $this->makeCompany();
        $schedule = $this->makeSchedule($company);
        $absenceType = $this->makeAbsenceType($company);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $path = UploadedFile::fake()->create('note.pdf', 100)->store('absences/proofs/'.$company->id, 'local');

        $absence = Absence::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'days_count' => 3,
            'status' => 'pending',
            'proof_path' => $path,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->get("/api/v1/absences/{$absence->id}/proof");

        $response->assertStatus(200);
    }

    public function test_other_employee_cannot_download_a_colleague_proof(): void
    {
        $company = $this->makeCompany();
        $schedule = $this->makeSchedule($company);
        $absenceType = $this->makeAbsenceType($company);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $otherEmployee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'other@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $path = UploadedFile::fake()->create('note.pdf', 100)->store('absences/proofs/'.$company->id, 'local');

        $absence = Absence::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'days_count' => 3,
            'status' => 'pending',
            'proof_path' => $path,
        ]);

        Sanctum::actingAs($otherEmployee);

        $response = $this->get("/api/v1/absences/{$absence->id}/proof");

        $response->assertStatus(403);
    }

    public function test_download_returns_404_when_no_proof_attached(): void
    {
        $company = $this->makeCompany();
        $schedule = $this->makeSchedule($company);
        $absenceType = $this->makeAbsenceType($company);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $absence = Absence::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'days_count' => 3,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->get("/api/v1/absences/{$absence->id}/proof");

        $response->assertStatus(404);
    }
}
