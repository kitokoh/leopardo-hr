<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Contrat SPA admin ↔ API — endpoints du cockpit super-admin créés côté
 * API (issue #1764) : /admin/dashboard/*, /admin/edge-nodes,
 * /admin/ai/conversations, /admin/fleet/alerts, /admin/hr-reports,
 * /admin/platform/marketing/oauth-config.
 */
class PlatformAdminDashboardApiTest extends TestCase
{
    use CreatesMvpSchema;

    protected Company $company;

    protected SuperAdmin $superAdmin;

    protected Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->company = Company::factory()->create();
        $this->superAdmin = SuperAdmin::query()->create([
            'name' => 'Super Admin Test',
            'email' => 'sa-admin-test@leopardo-rh.com',
            'password_hash' => bcrypt('secret123'),
            'role' => 'super_admin',
        ]);
        $this->manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function actingAsSuperAdmin(): void
    {
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');
    }

    /** @test */
    public function admin_endpoints_require_super_admin_auth(): void
    {
        $this->getJson('/api/v1/admin/dashboard/stats')->assertStatus(401);
        $this->getJson('/api/v1/admin/edge-nodes')->assertStatus(401);
        $this->getJson('/api/v1/admin/fleet/alerts')->assertStatus(401);
        $this->getJson('/api/v1/admin/ai/conversations')->assertStatus(401);
        $this->getJson('/api/v1/admin/hr-reports?type=headcount&start_date=2026-01-01&end_date=2026-12-31')->assertStatus(401);
    }

    /** @test */
    public function admin_endpoints_reject_non_super_admin(): void
    {
        // Un token employee ne s'authentifie pas sur le guard super_admin_api :
        // Sanctum répond 401 (le SPA admin n'utilise que des tokens super-admin).
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/admin/dashboard/stats')->assertStatus(401);
        $this->getJson('/api/v1/admin/dashboard/alerts')->assertStatus(401);
    }

    /** @test */
    public function dashboard_stats_returns_spa_contract_shape(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->getJson('/api/v1/admin/dashboard/stats')->assertStatus(200);

        $response->assertJsonStructure([
            'totalUsers',
            'totalCompanies',
            'activeSubscriptions',
            'monthlyRevenue',
            'newUsersToday',
            'newCompaniesToday',
            'supportTickets',
            'systemHealth',
        ]);
    }

    /** @test */
    public function dashboard_activities_and_alerts_return_lists(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/v1/admin/dashboard/activities')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);

        $alerts = $this->getJson('/api/v1/admin/dashboard/alerts')
            ->assertStatus(200)
            ->assertJsonStructure(['data'])
            ->json('data');

        // Chaque alerte expose l'identifiant utilisé pour le dismiss.
        foreach ($alerts as $alert) {
            $this->assertArrayHasKey('id', $alert);
            $this->assertArrayHasKey('level', $alert);
            $this->assertArrayHasKey('message', $alert);
        }
    }

    /** @test */
    public function dismissed_alert_is_filtered_and_persisted(): void
    {
        $this->actingAsSuperAdmin();

        // Alerte déterministe : un essai expirant sous 7 jours déclenche
        // l'alerte `trials_expiring`.
        Company::factory()->trial()->create([
            'subscription_end' => now()->addDays(3)->toDateString(),
        ]);

        $before = collect($this->getJson('/api/v1/admin/dashboard/alerts')->json('data'))->pluck('id');
        $this->assertTrue($before->contains('trials_expiring'), 'L\'alerte trials_expiring doit apparaître.');

        $this->postJson('/api/v1/admin/dashboard/alerts/trials_expiring/dismiss')->assertStatus(202);

        $this->assertDatabaseHas('platform_alert_dismissals', ['alert_key' => 'trials_expiring']);

        $after = collect($this->getJson('/api/v1/admin/dashboard/alerts')->json('data'))->pluck('id');
        $this->assertFalse($after->contains('trials_expiring'), 'L\'alerte dismissée ne doit plus apparaître.');
    }

    /** @test */
    public function edge_nodes_list_returns_data_wrapper(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/v1/admin/edge-nodes')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    /** @test */
    public function ai_conversations_endpoints_return_lists(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/v1/admin/ai/conversations')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/admin/ai/conversations/999999/messages')
            ->assertStatus(404)
            ->assertJsonPath('error', 'CONVERSATION_NOT_FOUND');
    }

    /** @test */
    public function fleet_alerts_returns_data_wrapper(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/v1/admin/fleet/alerts')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    /** @test */
    public function hr_reports_support_all_spa_types(): void
    {
        $this->actingAsSuperAdmin();

        foreach (['headcount', 'turnover', 'absenteeism', 'payroll_summary', 'training_progress'] as $type) {
            $response = $this->getJson("/api/v1/admin/hr-reports?type={$type}&start_date=2026-01-01&end_date=2026-12-31")
                ->assertStatus(200)
                ->assertJsonStructure(['data' => ['columns', 'rows']]);

            $this->assertIsArray($response->json('data.columns'), "columns pour {$type}");
            $this->assertIsArray($response->json('data.rows'), "rows pour {$type}");
        }
    }

    /** @test */
    public function hr_reports_reject_invalid_type(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/v1/admin/hr-reports?type=bogus&start_date=2026-01-01&end_date=2026-12-31')
            ->assertStatus(422);
    }

    /** @test */
    public function marketing_oauth_config_can_be_saved_and_read_without_secret(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson('/api/v1/admin/platform/marketing/oauth-config', [
            'provider' => 'linkedin',
            'client_id' => 'client-123',
            'redirect_uri' => 'https://app.leopardo-rh.com/oauth/linkedin/callback',
            'client_secret' => 'super-secret-value',
        ])->assertStatus(200)->assertJson(['status' => 'saved']);

        $row = DB::table('platform_oauth_configs')->where('provider', 'linkedin')->first();
        $this->assertNotNull($row);
        $this->assertSame('client-123', $row->client_id);
        $this->assertNotSame('super-secret-value', $row->client_secret_encrypted);
        $this->assertSame('super-secret-value', Crypt::decryptString($row->client_secret_encrypted));

        $config = $this->getJson('/api/v1/admin/platform/marketing/oauth-config')
            ->assertStatus(200)
            ->json('data.linkedin');

        $this->assertSame('client-123', $config['client_id']);
        $this->assertTrue($config['has_client_secret']);
        $this->assertArrayNotHasKey('client_secret', $config);
        $this->assertArrayNotHasKey('client_secret_encrypted', $config);
    }

    /** @test */
    public function marketing_oauth_config_rejects_unknown_provider(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson('/api/v1/admin/platform/marketing/oauth-config', [
            'provider' => 'tiktok',
            'client_id' => 'x',
            'redirect_uri' => 'https://example.com/cb',
        ])->assertStatus(422);
    }
}
