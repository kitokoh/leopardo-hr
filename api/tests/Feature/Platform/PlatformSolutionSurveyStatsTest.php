<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Auth\Domain\Models\SuperAdmin;
use App\Modules\Marketing\Domain\Models\MarketingLead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #6694 (BC-25) — endpoints admin de stats des surveys de solutions.
 *
 * Couvre : agrégats (volume par solution, packs suggérés, distribution des
 * réponses, conversion), borne limit (max 1000), auth super_admin requise.
 */
class PlatformSolutionSurveyStatsTest extends TestCase
{
    use RefreshTenantDatabase;

    private function createLead(array $payload, ?string $convertedCompanyId = null): MarketingLead
    {
        return MarketingLead::query()->create([
            'external_id' => (string) Str::uuid(),
            'type' => MarketingLead::TYPE_SOLUTION_SURVEY,
            'email' => 'prospect-'.uniqid().'@example.com',
            'locale' => 'fr',
            'country' => 'DZ',
            'page' => '/restaurant',
            'source' => 'wizard_restaurant',
            'payload' => $payload,
            'status' => MarketingLead::STATUS_NEW,
            'converted_company_id' => $convertedCompanyId,
            'captured_at' => now(),
        ]);
    }

    private function actingAsSuperAdmin(): void
    {
        /** @var SuperAdmin $superAdmin */
        $superAdmin = new SuperAdmin([
            'name' => 'Super Admin Survey',
            'email' => 'sa-survey@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => bcrypt('secret123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');
    }

    public function test_stats_require_super_admin_auth(): void
    {
        $this->getJson('/api/v1/admin/solutions/survey-stats')
            ->assertStatus(401);
    }

    public function test_stats_aggregate_survey_leads(): void
    {
        $this->actingAsSuperAdmin();

        $this->createLead([
            'solution' => 'restaurant',
            'answers' => ['size' => '11-50', 'delivery' => true],
            'packages' => ['manager_app', 'kiosk'],
        ], convertedCompanyId: 'company-1');

        $this->createLead([
            'solution' => 'restaurant',
            'answers' => ['size' => '1-10', 'delivery' => false],
            'packages' => ['employee_app'],
        ]);

        $response = $this->getJson('/api/v1/admin/solutions/survey-stats')
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertSame(2, $data['totals']['responses']);
        $this->assertSame(1, $data['totals']['converted']);
        $this->assertSame(0.5, $data['totals']['conversion_rate']);

        $this->assertSame('restaurant', $data['by_solution'][0]['solution']);
        $this->assertSame(2, $data['by_solution'][0]['responses']);

        $packageKeys = array_column($data['packages'], 'key');
        $this->assertContains('kiosk', $packageKeys);
        $this->assertContains('employee_app', $packageKeys);

        $questionKeys = array_column($data['answers'], 'question');
        $this->assertContains('size', $questionKeys);
        $this->assertContains('delivery', $questionKeys);

        foreach ($data['answers'] as $question) {
            $total = array_sum(array_column($question['values'], 'count'));
            $this->assertSame($question['total'], $total);
        }
    }

    public function test_stats_respect_limit_bound(): void
    {
        $this->actingAsSuperAdmin();

        foreach (range(1, 5) as $i) {
            $this->createLead(['solution' => 'restaurant', 'answers' => [], 'packages' => []]);
        }

        $data = $this->getJson('/api/v1/admin/solutions/survey-stats?limit=3')
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(3, $data['totals']['responses']);
        $this->assertSame(3, $data['window']['limit']);

        // limit plafonné à 1000, jamais plus.
        $data = $this->getJson('/api/v1/admin/solutions/survey-stats?limit=99999')
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(1000, $data['window']['limit']);
        $this->assertSame(5, $data['totals']['responses']);
    }

    public function test_stats_empty_dataset(): void
    {
        $this->actingAsSuperAdmin();

        $data = $this->getJson('/api/v1/admin/solutions/survey-stats')
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(0, $data['totals']['responses']);
        $this->assertSame(0.0, $data['totals']['conversion_rate']);
        $this->assertSame([], $data['by_solution']);
        $this->assertSame([], $data['packages']);
        $this->assertSame([], $data['answers']);
    }

    public function test_stats_ignore_other_lead_types(): void
    {
        $this->actingAsSuperAdmin();

        DB::table('marketing_leads')->insert([
            'external_id' => (string) Str::uuid(),
            'type' => MarketingLead::TYPE_SIGNUP,
            'email' => 'signup@example.com',
            'locale' => 'fr',
            'country' => 'DZ',
            'payload' => json_encode(['solution' => 'restaurant']),
            'status' => MarketingLead::STATUS_NEW,
            'captured_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = $this->getJson('/api/v1/admin/solutions/survey-stats')
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(0, $data['totals']['responses']);
    }
}
