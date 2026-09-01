<?php

declare(strict_types=1);

namespace Tests\Feature\Solutions;

use Tests\TestCase;

/**
 * Endpoints publics de pré-qualification des solutions (vitrine).
 *
 * Ces endpoints ne touchent aucune table : pas besoin de RefreshDatabase.
 */
class SolutionSurveyEndpointTest extends TestCase
{
    public function test_index_lists_available_solutions(): void
    {
        $response = $this->getJson('/api/v1/solutions');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['code', 'name', 'description', 'maturity']]]);

        $codes = array_column($response->json('data'), 'code');
        $this->assertContains('restaurant', $codes);
    }

    public function test_questions_returns_survey_shape_for_restaurant(): void
    {
        $response = $this->getJson('/api/v1/solutions/restaurant/survey');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'code',
                'questions' => [['key', 'type', 'label_key']],
                'packages' => [['key', 'type', 'label_key']],
            ],
        ]);
        $response->assertJsonPath('data.code', 'restaurant');
    }

    public function test_suggest_returns_pack_for_a_restaurant_profile(): void
    {
        $response = $this->postJson('/api/v1/solutions/restaurant/survey', [
            'answers' => [
                'service_type' => 'mixte',
                'employee_count' => '6_20',
                'attendance_device' => 'kiosk',
                'scheduling' => true,
                'payroll' => true,
                'delivery' => 'own',
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.code', 'restaurant');

        $packages = $response->json('data.packages');
        $this->assertNotEmpty($packages);
        $this->assertSame($response->json('data.total'), count($packages));

        $keys = array_column($packages, 'key');
        $this->assertContains('mobile_employee', $keys);
        $this->assertContains('kiosk', $keys);
        $this->assertContains('edge', $keys);
        $this->assertContains('payroll', $keys);
    }

    public function test_unknown_solution_is_rejected(): void
    {
        $this->getJson('/api/v1/solutions/unknown/survey')->assertStatus(404);
        $this->postJson('/api/v1/solutions/unknown/survey', ['answers' => []])->assertStatus(404);
    }

    public function test_missing_answers_are_rejected(): void
    {
        $this->postJson('/api/v1/solutions/restaurant/survey', [])->assertStatus(422);
    }
}
