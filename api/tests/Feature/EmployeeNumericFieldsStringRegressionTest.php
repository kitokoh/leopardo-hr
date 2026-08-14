<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\Schedule;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Régression #1765 — POST /api/v1/employees : les champs numériques envoyés en
 * chaîne JSON (formulaires HTML, champs texte Flutter, curl) provoquaient un
 * TypeError non traité → HTTP 500, car CreateEmployeeDTO/UpdateEmployeeDTO
 * sont typés (float/int/bool) alors que la validation accepte les chaînes.
 */
class EmployeeNumericFieldsStringRegressionTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create();
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = $manager;

        Sanctum::actingAs($this->manager);
    }

    private function createSchedule(): Schedule
    {
        /** @var Schedule $schedule */
        $schedule = Schedule::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Equipe reg',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'break_minutes' => 20,
            'work_days' => [1, 2, 3, 4, 5],
            'late_tolerance_minutes' => 5,
            'overtime_threshold_daily' => 8,
            'overtime_threshold_weekly' => 40,
            'is_default' => false,
        ]);

        return $schedule;
    }

    public function test_create_employee_accepts_numeric_and_boolean_fields_as_strings(): void
    {
        $schedule = $this->createSchedule();

        $response = $this->postJson('/api/v1/employees', [
            'first_name' => 'Ali',
            'last_name' => 'Said',
            'email' => 'ali.said.string@example.test',
            'hire_date' => '2026-08-01',
            'password' => 'password123',
            'role' => 'employee',
            'salary_type' => 'hourly',
            'salary_base' => '40000',
            'hourly_rate' => '1200.5',
            'schedule_id' => (string) $schedule->id,
            'biometric_face_enabled' => 'true',
            'biometric_fingerprint_enabled' => '0',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.salary_base', 40000)
            ->assertJsonPath('data.hourly_rate', 1200.5)
            ->assertJsonPath('data.schedule_id', $schedule->id)
            ->assertJsonPath('data.biometric_face_enabled', true)
            ->assertJsonPath('data.biometric_fingerprint_enabled', false);

        /** @var Employee $created */
        $created = Employee::query()->where('email', 'ali.said.string@example.test')->firstOrFail();

        $this->assertSame(40000.0, (float) $created->salary_base);
        $this->assertSame(1200.5, (float) $created->hourly_rate);
        $this->assertSame($schedule->id, (int) $created->schedule_id);
        $this->assertTrue((bool) $created->biometric_face_enabled);
        $this->assertFalse((bool) $created->biometric_fingerprint_enabled);
    }

    public function test_update_employee_accepts_numeric_and_boolean_fields_as_strings(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'salary_type' => 'fixed',
            'salary_base' => 30000,
        ]);

        $this->putJson("/api/v1/employees/{$employee->id}", [
            'salary_base' => '45000',
            'hourly_rate' => '950.75',
            'biometric_face_enabled' => 'true',
            'biometric_fingerprint_enabled' => 'false',
        ])->assertOk()
            ->assertJsonPath('data.salary_base', 45000)
            ->assertJsonPath('data.hourly_rate', 950.75)
            ->assertJsonPath('data.biometric_face_enabled', true)
            ->assertJsonPath('data.biometric_fingerprint_enabled', false);

        $employee->refresh();

        $this->assertSame(45000.0, (float) $employee->salary_base);
        $this->assertSame(950.75, (float) $employee->hourly_rate);
        $this->assertTrue((bool) $employee->biometric_face_enabled);
        $this->assertFalse((bool) $employee->biometric_fingerprint_enabled);
    }
}
