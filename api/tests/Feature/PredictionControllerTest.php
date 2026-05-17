<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PredictionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();

        $this->manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        $this->employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
    }

    public function test_turnover_prediction_requires_manager_role(): void
    {
        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/v1/predictions/turnover');

        $response->assertForbidden();
    }

    public function test_turnover_prediction_accessible_by_manager(): void
    {
        DB::statement("SET search_path TO company_{$this->company->id}, public");

        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/v1/predictions/turnover');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'risk_score',
                    'high_risk_employees',
                    'department_risks',
                    'overall_turnover_rate',
                ],
            ]);
    }

    public function test_absenteeism_prediction_requires_manager_role(): void
    {
        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/v1/predictions/absenteeism');

        $response->assertForbidden();
    }

    public function test_absenteeism_prediction_accessible_by_manager(): void
    {
        DB::statement("SET search_path TO company_{$this->company->id}, public");

        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/v1/predictions/absenteeism');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'predicted_days_next_month',
                    'high_risk_periods',
                    'department_predictions',
                    'recommendations',
                ],
            ]);
    }

    public function test_proactive_notifications_accessible_by_manager(): void
    {
        DB::statement("SET search_path TO company_{$this->company->id}, public");

        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/v1/predictions/notifications');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_proactive_notifications_requires_manager_role(): void
    {
        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/v1/predictions/notifications');

        $response->assertForbidden();
    }
}
