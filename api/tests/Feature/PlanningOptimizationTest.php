<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();

        $this->manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
    }

    public function test_weekly_optimization_returns_planning_data(): void
    {

        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/v1/planning/weekly-optimization');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'week',
                    'total_employees',
                    'total_absences',
                    'department_coverage',
                    'conflicts',
                    'recommendations',
                    'optimization_score',
                ],
            ]);

        $data = $response->json('data');
        $this->assertIsInt($data['total_employees']);
        $this->assertIsInt($data['optimization_score']);
        $this->assertGreaterThanOrEqual(0, $data['optimization_score']);
        $this->assertLessThanOrEqual(100, $data['optimization_score']);
    }

    public function test_weekly_optimization_accepts_custom_week(): void
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/v1/planning/weekly-optimization?week_start=2026-06-01');

        $response->assertOk();
        $this->assertStringContains('2026-06', $response->json('data.week'));
    }

    public function test_shift_rebalancing_returns_department_analysis(): void
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/v1/planning/shift-rebalancing');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'departments',
                    'average_size',
                    'suggestions',
                ],
            ]);
    }

    public function test_planning_requires_authentication(): void
    {
        $this->getJson('/api/v1/planning/weekly-optimization')
            ->assertUnauthorized();

        $this->getJson('/api/v1/planning/shift-rebalancing')
            ->assertUnauthorized();
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '$haystack' contains '$needle'."
        );
    }
}
