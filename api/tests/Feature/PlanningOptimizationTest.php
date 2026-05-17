<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTenantSetup;

class PlanningOptimizationTest extends TestCase
{
    use RefreshDatabase;
    use WithTenantSetup;

    public function test_weekly_optimization_returns_planning_data(): void
    {
        $user = $this->createTenantManager();

        $response = $this->actingAs($user, 'sanctum')
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
        $user = $this->createTenantManager();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/planning/weekly-optimization?week_start=2026-06-01');

        $response->assertOk();
        $this->assertStringContains('2026-06', $response->json('data.week'));
    }

    public function test_shift_rebalancing_returns_department_analysis(): void
    {
        $user = $this->createTenantManager();

        $response = $this->actingAs($user, 'sanctum')
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
