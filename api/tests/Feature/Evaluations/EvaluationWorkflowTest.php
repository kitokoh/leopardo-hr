<?php

namespace Tests\Feature\Evaluations;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Evaluation;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class EvaluationWorkflowTest extends TestCase
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

    public function test_manager_can_create_submit_and_delete_draft_evaluations(): void
    {
        [$company, $manager, $employee] = $this->createCompanyActors();

        Sanctum::actingAs($manager);

        $store = $this->postJson('/api/v1/evaluations', [
            'employee_id' => $employee->id,
            'period' => '2026-Q1',
            'score' => 4.5,
            'criteria' => [
                ['label' => 'Qualite', 'score' => 4.5],
                ['label' => 'Ponctualite', 'score' => 4.0],
            ],
            'strengths' => 'Fiable',
            'improvements' => 'Communication',
            'overall_comment' => 'Bon trimestre',
        ]);

        $store->assertStatus(201);
        $store->assertJsonPath('data.employee_id', $employee->id);
        $store->assertJsonPath('data.evaluator_id', $manager->id);
        $store->assertJsonPath('data.status', 'draft');

        $evaluationId = $store->json('data.id');

        $submit = $this->putJson("/api/v1/evaluations/{$evaluationId}/submit");

        $submit->assertOk();
        $submit->assertJsonPath('data.status', 'submitted');

        $submittedDelete = $this->deleteJson("/api/v1/evaluations/{$evaluationId}");
        $submittedDelete->assertStatus(422);
        $submittedDelete->assertJsonPath('error.code', 'EVALUATION_NOT_DRAFT');

        $draft = Evaluation::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'evaluator_id' => $manager->id,
            'period' => '2026-Q2',
            'status' => 'draft',
        ]);

        $deleteDraft = $this->deleteJson("/api/v1/evaluations/{$draft->id}");
        $deleteDraft->assertOk();

        $this->assertDatabaseMissing('evaluations', [
            'id' => $draft->id,
        ]);
    }

    public function test_employee_sees_only_own_evaluations_and_can_acknowledge_submitted_one(): void
    {
        [$company, $manager, $employee] = $this->createCompanyActors();

        $otherEmployee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'other.employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $ownEvaluation = Evaluation::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'evaluator_id' => $manager->id,
            'period' => '2026-Q1',
            'status' => 'submitted',
        ]);

        $otherEvaluation = Evaluation::query()->create([
            'company_id' => $company->id,
            'employee_id' => $otherEmployee->id,
            'evaluator_id' => $manager->id,
            'period' => '2026-Q1',
            'status' => 'submitted',
        ]);

        Sanctum::actingAs($employee);

        $index = $this->getJson('/api/v1/evaluations');
        $index->assertOk();
        $index->assertJsonCount(1, 'data');
        $index->assertJsonPath('data.0.id', $ownEvaluation->id);

        $showOther = $this->getJson("/api/v1/evaluations/{$otherEvaluation->id}");
        $showOther->assertStatus(403);

        $ack = $this->putJson("/api/v1/evaluations/{$ownEvaluation->id}/acknowledge");
        $ack->assertOk();
        $ack->assertJsonPath('data.status', 'acknowledged');
        $this->assertNotNull($ack->json('data.acknowledged_at'));
    }

    public function test_duplicate_create_acknowledged_update_and_non_submitted_acknowledge_are_blocked(): void
    {
        [$company, $manager, $employee] = $this->createCompanyActors();

        Evaluation::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'evaluator_id' => $manager->id,
            'period' => '2026-Q1',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($manager);

        $duplicate = $this->postJson('/api/v1/evaluations', [
            'employee_id' => $employee->id,
            'period' => '2026-Q1',
        ]);

        $duplicate->assertStatus(422);
        $duplicate->assertJsonPath('error.code', 'EVALUATION_ALREADY_EXISTS');

        $acknowledged = Evaluation::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'evaluator_id' => $manager->id,
            'period' => '2026-Q2',
            'status' => 'acknowledged',
        ]);

        $update = $this->patchJson("/api/v1/evaluations/{$acknowledged->id}", [
            'strengths' => 'Updated text',
        ]);

        $update->assertStatus(422);
        $update->assertJsonPath('error.code', 'EVALUATION_ALREADY_ACKNOWLEDGED');

        Sanctum::actingAs($employee);

        $notSubmitted = Evaluation::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'evaluator_id' => $manager->id,
            'period' => '2026-Q3',
            'status' => 'draft',
        ]);

        $ackDraft = $this->putJson("/api/v1/evaluations/{$notSubmitted->id}/acknowledge");
        $ackDraft->assertStatus(422);
        $ackDraft->assertJsonPath('error.code', 'EVALUATION_NOT_SUBMITTED');
    }

    private function createCompanyActors(): array
    {
        $company = Company::query()->create([
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

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        return [$company, $manager, $employee];
    }
}
