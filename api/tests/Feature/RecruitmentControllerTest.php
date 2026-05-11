<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\JobPosting;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class RecruitmentControllerTest extends TestCase
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

    public function test_manager_can_list_job_postings(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'rh',
        ]);

        JobPosting::create([
            'company_id' => $company->id,
            'created_by' => $manager->id,
            'title' => 'Developpeur Backend',
            'status' => 'published',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/recruitment/jobs');
        $response->assertOk();
    }

    public function test_rh_manager_can_create_job_posting(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'rh',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/recruitment/jobs', [
            'title' => 'Ingenieur DevOps',
            'description' => 'Poste DevOps senior',
            'remote_policy' => 'hybrid',
            'contract_type' => 'cdi',
            'salary_range_min' => 50000,
            'salary_range_max' => 70000,
            'currency' => 'EUR',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Ingenieur DevOps');
        $response->assertJsonPath('data.status', 'draft');
    }

    public function test_employee_cannot_create_job_posting(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/recruitment/jobs', [
            'title' => 'Test',
        ])->assertStatus(403);
    }

    public function test_manager_can_update_job_posting(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'rh',
        ]);

        $job = JobPosting::create([
            'company_id' => $company->id,
            'created_by' => $manager->id,
            'title' => 'Old Title',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->putJson("/api/v1/recruitment/jobs/{$job->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Updated Title');
    }

    public function test_manager_can_publish_job(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'manager_role' => 'rh',
        ]);

        $job = JobPosting::create([
            'company_id' => $company->id,
            'created_by' => $manager->id,
            'title' => 'Dev PHP',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/recruitment/jobs/{$job->id}/publish");
        $response->assertOk();
        $response->assertJsonPath('data.status', 'published');
    }
}
