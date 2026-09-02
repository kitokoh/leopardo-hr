<?php

declare(strict_types=1);

namespace Tests\Feature\Solutions;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #6694 — persistance des réponses au survey de solutions (wizard
 * vitrine) + écran admin de stats (volume par solution, packs suggérés,
 * conversion survey → inscription).
 */
class SolutionSurveyStatsTest extends TestCase
{
    use RefreshTenantDatabase;

    private function submitSurvey(array $answers = []): void
    {
        $this->postJson('/api/v1/solutions/restaurant/survey', [
            'answers' => $answers !== [] ? $answers : [
                'service_type' => 'mixte',
                'employee_count' => '6_20',
                'attendance_device' => 'kiosk',
                'scheduling' => true,
                'payroll' => true,
                'delivery' => 'own',
            ],
        ])->assertOk();
    }

    public function test_survey_submission_is_persisted_anonymously(): void
    {
        $this->submitSurvey();

        $this->assertDatabaseCount('solution_survey_responses', 1);
        $row = DB::table('solution_survey_responses')->first();
        $this->assertSame('restaurant', $row->solution_code);
        $this->assertGreaterThan(0, $row->total_packages);

        $answers = json_decode((string) $row->answers, true);
        $this->assertSame('mixte', $answers['service_type']);
    }

    public function test_survey_submission_with_lead_email_stores_hash_only(): void
    {
        $this->postJson('/api/v1/solutions/restaurant/survey', [
            'answers' => ['service_type' => 'sur_place'],
            'lead_email' => 'prospect@restaurant.example',
        ])->assertOk();

        $row = DB::table('solution_survey_responses')->first();
        $this->assertNotNull($row->lead_email_hash);
        $this->assertSame(hash('sha256', 'prospect@restaurant.example'), $row->lead_email_hash);
        // Jamais d'email en clair (RGPD).
        $this->assertStringNotContainsString('prospect', json_encode($row, JSON_UNESCAPED_SLASHES));
    }

    public function test_admin_survey_stats_requires_super_admin(): void
    {
        $this->getJson('/api/v1/admin/solutions/surveys')->assertUnauthorized();
    }

    public function test_admin_survey_stats_returns_aggregates(): void
    {
        $this->submitSurvey();
        $this->submitSurvey(['service_type' => 'sur_place', 'employee_count' => '1_5']);

        /** @var SuperAdmin $superAdmin */
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin-survey-stats@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $response = $this->getJson('/api/v1/admin/solutions/surveys?days=30');

        $response->assertOk()
            ->assertJsonPath('data.total_responses', 2)
            ->assertJsonPath('data.per_solution.0.code', 'restaurant')
            ->assertJsonPath('data.per_solution.0.responses', 2)
            ->assertJsonPath('data.conversion.survey_responses', 2)
            ->assertJsonStructure([
                'data' => [
                    'top_packages' => [],
                    'conversion' => ['rate'],
                ],
            ]);
    }
}
