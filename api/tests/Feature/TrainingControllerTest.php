<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\TrainingCourse;
use App\Models\TrainingSession;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class TrainingControllerTest extends TestCase
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

    public function test_anyone_can_list_courses(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        TrainingCourse::create([
            'company_id' => $company->id,
            'title' => 'Formation Securite',
            'type' => 'internal',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/training/courses');
        $response->assertOk();
    }

    public function test_rh_can_create_course(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'rh',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/training/courses', [
            'title' => 'Formation Laravel',
            'type' => 'external',
            'provider' => 'Laracasts',
            'duration_hours' => 40,
            'cost_per_participant' => 500,
            'currency' => 'EUR',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Formation Laravel');
    }

    public function test_employee_cannot_create_course(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/training/courses', [
            'title' => 'Test',
            'type' => 'internal',
        ])->assertStatus(403);
    }

    public function test_rh_can_create_session(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'rh',
        ]);

        $course = TrainingCourse::create([
            'company_id' => $company->id,
            'title' => 'Formation PHP',
            'type' => 'internal',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/training/courses/{$course->id}/sessions", [
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'location' => 'Salle A',
            'external_trainer' => 'Mohamed',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.location', 'Salle A');
    }

    public function test_employee_can_enroll_in_session(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        $course = TrainingCourse::create([
            'company_id' => $company->id,
            'title' => 'Formation',
            'type' => 'internal',
        ]);

        $session = TrainingSession::create([
            'training_course_id' => $course->id,
            'company_id' => $company->id,
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeeks(2),
            'status' => 'planned',
        ]);

        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/training/sessions/{$session->id}/enroll", [
            'employee_id' => $employee->id,
        ]);
        $response->assertStatus(201);
    }
}
