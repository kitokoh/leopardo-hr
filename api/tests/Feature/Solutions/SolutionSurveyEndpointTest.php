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

    public function test_pack_pdf_is_downloadable(): void
    {
        $response = $this->get('/api/v1/solutions/restaurant/pack?packages=mobile_employee,kiosk,edge');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pack_pdf_rejects_unknown_or_missing_packages(): void
    {
        $this->getJson('/api/v1/solutions/restaurant/pack?packages=mobile_employee,unknown')->assertStatus(422);
        $this->getJson('/api/v1/solutions/restaurant/pack')->assertStatus(422);
        $this->getJson('/api/v1/solutions/unknown/pack?packages=mobile_employee')->assertStatus(404);
    }

    /**
     * BOS-009 (#8227) — TravelAgency est le vertical PILOTE de l'onboarding
     * intelligent, mais il etait le seul sans survey : l'endpoint repondait
     * 404 `SOLUTION_SURVEY_NOT_FOUND`, donc le parcours « je decris mon
     * activite -> workspace pret » ne pouvait pas demarrer pour une agence.
     */
    public function test_questions_returns_survey_shape_for_travelagency(): void
    {
        $response = $this->getJson('/api/v1/solutions/travelagency/survey');

        $response->assertOk();
        $response->assertJsonPath('data.code', 'travelagency');

        $questions = $response->json('data.questions');
        $this->assertNotEmpty($questions);

        // Les questions specifiques au metier d'agence doivent etre presentes :
        // sans elles le survey serait un clone de Restaurant et ne decrirait
        // pas l'activite reelle (reseau de bureaux, vente en ligne).
        $keys = array_column($questions, 'key');
        $this->assertContains('agency_type', $keys);
        $this->assertContains('offices_count', $keys);
        $this->assertContains('online_sales', $keys);
    }

    public function test_suggest_returns_pack_for_a_travel_agency_profile(): void
    {
        $response = $this->postJson('/api/v1/solutions/travelagency/survey', [
            'answers' => [
                'agency_type' => 'tour_operator',
                'employee_count' => '6_20',
                'offices_count' => '2_5',
                'attendance_device' => 'kiosk',
                'scheduling' => true,
                'payroll' => true,
                'online_sales' => true,
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.code', 'travelagency');

        $packages = $response->json('data.packages');
        $this->assertNotEmpty($packages);
        $this->assertSame($response->json('data.total'), count($packages));

        $keys = array_column($packages, 'key');
        $this->assertContains('mobile_employee', $keys);
        $this->assertContains('mobile_manager', $keys);
        $this->assertContains('kiosk', $keys);
        $this->assertContains('edge', $keys);
        $this->assertContains('online_shop', $keys);
        $this->assertContains('reporting', $keys);
    }

    public function test_travel_pack_pdf_is_downloadable(): void
    {
        $response = $this->get('/api/v1/solutions/travelagency/pack?packages=mobile_employee,online_shop');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
