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

    protected function setUp(): void
    {
        parent::setUp();

        // `solution_survey_responses` est une table PUBLIQUE créée par la
        // fixture MVP : elle n'est pas couverte par l'isolation par transaction
        // du tenant, et le contrôleur écrit hors de toute transaction de test.
        // Sans ce nettoyage, les lignes laissées par les tests précédents
        // s'accumulent (`Entries found: 3` au lieu de 1).
        DB::table('solution_survey_responses')->delete();
    }

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
        $this->getJson('/api/v1/admin/solutions/survey-stats')->assertUnauthorized();
    }

    public function test_admin_survey_stats_returns_aggregates(): void
    {
        // #8020 : l'écran admin agrège les LEADS `solution_survey` de
        // `marketing_leads` (pas la table `solution_survey_responses`, qui
        // sert la persistance anonyme du wizard). On sème donc la source réelle.
        DB::table('marketing_leads')->insert([
            [
                'external_id' => 'survey-1',
                'email' => 'lead1@restaurant.example',
                'type' => 'solution_survey',
                'source' => 'restaurant',
                'payload' => json_encode(['solution' => 'restaurant', 'packages' => ['pro'], 'answers' => ['service_type' => 'mixte']]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'external_id' => 'survey-2',
                'email' => 'lead2@restaurant.example',
                'type' => 'solution_survey',
                'source' => 'restaurant',
                'payload' => json_encode(['solution' => 'restaurant', 'packages' => ['pro'], 'answers' => ['service_type' => 'sur_place']]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        /** @var SuperAdmin $superAdmin */
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin-survey-stats@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $response = $this->getJson('/api/v1/admin/solutions/survey-stats?days=30');

        // #8020 : le contrat de réponse est `data.totals.responses` /
        // `data.by_solution[].solution` (agrégats de `marketing_leads`),
        // pas l'ancien `data.total_responses` / `data.per_solution`.
        $response->assertOk()
            ->assertJsonPath('data.totals.responses', 2)
            ->assertJsonPath('data.by_solution.0.solution', 'restaurant')
            ->assertJsonPath('data.by_solution.0.responses', 2)
            ->assertJsonStructure([
                'data' => [
                    'totals' => ['responses', 'converted', 'conversion_rate'],
                    'by_solution' => [],
                    'packages' => [],
                    'answers' => [],
                ],
            ]);
    }
}
