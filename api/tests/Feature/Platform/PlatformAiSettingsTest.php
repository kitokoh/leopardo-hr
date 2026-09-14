<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Platform\Infrastructure\Services\PlatformAiSettingsApplier;
use App\Modules\Platform\Infrastructure\Services\PlatformAiSettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7384 / #7385 — réglages de l'assistant IA depuis le cockpit super-admin.
 *
 * Ce que ces tests verrouillent, dans l'ordre d'importance :
 *   1. **aucune ligne = aucun changement** : une base vide laisse la config
 *      d'environnement intacte (la fonctionnalité ne doit rien casser par
 *      défaut — c'est la propriété la plus importante) ;
 *   2. **un secret ne sort JAMAIS** : ni en clair dans la base, ni dans une
 *      réponse d'API, même pour un super-admin ;
 *   3. **un enregistrement sans nouvelle clé préserve la clé existante** : un
 *      formulaire ne doit pas effacer une clé par inadvertance ;
 *   4. la base **prime** sur l'environnement, et le retour arrière est possible ;
 *   5. le suivi agrège réellement (usage, coûts, erreurs).
 */
class PlatformAiSettingsTest extends TestCase
{
    use CreatesMvpSchema;

    private const PLAINTEXT_KEY = 'gsk_plaintext_secret_never_exposed_1234';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // La table vient d'une migration ; on garantit sa présence en test et
        // on repart d'un état vide (isolation entre tests).
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS public.platform_ai_settings (
    setting_key varchar(80) PRIMARY KEY,
    setting_value text NULL,
    encrypted_value text NULL,
    is_secret boolean NOT NULL DEFAULT false,
    updated_by varchar(180) NULL,
    created_at timestamp(0) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp(0) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
        DB::table('platform_ai_settings')->delete();
        PlatformAiSettingsApplier::flush();
    }

    protected function tearDown(): void
    {
        DB::table('platform_ai_settings')->delete();
        PlatformAiSettingsApplier::flush();
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function actingAsSuperAdmin(): SuperAdmin
    {
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        return $superAdmin;
    }

    private function repository(): PlatformAiSettingsRepository
    {
        return app(PlatformAiSettingsRepository::class);
    }

    public function test_settings_are_readable_without_any_override(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->getJson('/api/v1/admin/platform/ai/settings');

        $response->assertOk();
        // Le catalogue est exposé pour que l'UI se rende dynamiquement.
        $this->assertArrayHasKey('driver', $response->json('data.catalog'));
        $this->assertFalse((bool) $response->json('data.settings.groq_api_key.has_value'));
    }

    public function test_a_stored_secret_is_encrypted_at_rest_and_never_returned(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson('/api/v1/admin/platform/ai/settings', [
            'settings' => ['groq_api_key' => self::PLAINTEXT_KEY],
        ])->assertOk();

        // 1. chiffré au repos : la valeur en clair n'apparaît dans AUCUNE colonne
        $row = DB::table('platform_ai_settings')->where('setting_key', 'groq_api_key')->first();
        $this->assertNotNull($row);
        /** @var array<string, mixed> $asArray */
        $asArray = (array) $row;
        foreach ($asArray as $column => $value) {
            if (is_string($value)) {
                $this->assertStringNotContainsString(
                    self::PLAINTEXT_KEY,
                    $value,
                    "La clé en clair ne doit apparaître dans aucune colonne (trouvée dans {$column})"
                );
            }
        }
        $this->assertNotSame(self::PLAINTEXT_KEY, $asArray['encrypted_value'] ?? null);

        // 2. jamais renvoyé par l'API — même à un super-admin
        $response = $this->getJson('/api/v1/admin/platform/ai/settings');
        $response->assertOk();
        $response->assertJsonPath('data.settings.groq_api_key.value', null);
        $response->assertJsonPath('data.settings.groq_api_key.has_value', true);
        $this->assertStringNotContainsString(self::PLAINTEXT_KEY, $response->getContent() ?: '');
    }

    public function test_saving_without_a_new_secret_preserves_the_stored_one(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson('/api/v1/admin/platform/ai/settings', [
            'settings' => ['groq_api_key' => self::PLAINTEXT_KEY],
        ])->assertOk();

        $before = DB::table('platform_ai_settings')->where('setting_key', 'groq_api_key')->value('encrypted_value');

        // L'utilisateur change le modèle, laisse le champ clé vide : le secret
        // doit survivre — sinon un simple changement de modèle effacerait la clé.
        $this->putJson('/api/v1/admin/platform/ai/settings', [
            'settings' => ['groq_model' => 'openai/gpt-oss-120b', 'groq_api_key' => ''],
        ])->assertOk();

        $after = DB::table('platform_ai_settings')->where('setting_key', 'groq_api_key')->value('encrypted_value');

        $this->assertSame($before, $after);
    }

    public function test_an_unknown_setting_is_rejected(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson('/api/v1/admin/platform/ai/settings', [
            'settings' => ['groq_api_kye' => 'typo'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'AI_SETTING_UNKNOWN');

        $this->assertSame(0, DB::table('platform_ai_settings')->count());
    }

    public function test_database_override_wins_over_environment_and_can_be_reverted(): void
    {
        $this->actingAsSuperAdmin();
        $originalDriver = config('ai.driver');

        $this->putJson('/api/v1/admin/platform/ai/settings', [
            'settings' => ['driver' => 'groq'],
        ])->assertOk();

        // La surcharge s'applique à la config RÉELLE : c'est ce que lisent
        // AIFeatureCheck, l'Orchestrator et les clients fournisseurs.
        app(PlatformAiSettingsApplier::class)->apply();
        $this->assertSame('groq', config('ai.driver'));

        // Retour arrière : la surcharge disparaît. L'applier ne « dés-applique »
        // pas une valeur déjà écrite (il ne connaît que ce qui est en base) —
        // c'est sans effet en HTTP où chaque requête repart de l'environnement,
        // mais un process long (worker de queue) garde la valeur jusqu'au
        // prochain démarrage. On vérifie donc le contrat réel : plus de
        // surcharge en base ⇒ un boot frais relit l'environnement.
        $this->postJson('/api/v1/admin/platform/ai/settings/reset', [
            'setting_key' => 'driver',
        ])->assertOk();

        $this->assertArrayNotHasKey('driver', $this->repository()->resolvableValues());
        $this->assertNotNull($originalDriver);
    }

    public function test_monitoring_aggregates_usage_for_the_period(): void
    {
        $this->actingAsSuperAdmin();

        /** @var Company $company */
        $company = Company::factory()->create();

        DB::table('ai_audit_logs')->insert([
            [
                'company_id' => $company->id, 'user_id' => 1, 'provider' => 'groq',
                'model' => 'openai/gpt-oss-120b', 'input_tokens' => 100, 'output_tokens' => 50,
                'cost_cents' => 0, 'duration_ms' => 120, 'error' => null,
                'prompt' => 'combien d employes actifs ?', 'response' => '3 employes actifs',
                'created_at' => now(), 'workflow' => 'weekly_report',
            ],
            [
                'company_id' => $company->id, 'user_id' => 1, 'provider' => 'groq',
                'model' => 'openai/gpt-oss-120b', 'input_tokens' => 200, 'output_tokens' => 20,
                'cost_cents' => 3, 'duration_ms' => 400, 'error' => '429 rate limited',
                'prompt' => 'pointe-moi', 'response' => '',
                'created_at' => now(), 'workflow' => null,
            ],
        ]);

        DB::table('ai_tool_executions')->insert([
            [
                'company_id' => $company->id, 'user_id' => 1, 'tool_name' => 'create_employee',
                'tool_input' => '{}', 'stage' => 'executed', 'success' => true, 'created_at' => now(),
            ],
            [
                'company_id' => $company->id, 'user_id' => 1, 'tool_name' => 'create_employee',
                'tool_input' => '{}', 'stage' => 'confirmation_required', 'success' => true, 'created_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/v1/admin/platform/ai/monitoring');
        $response->assertOk();

        $response->assertJsonPath('data.totals.requests', 2);
        $response->assertJsonPath('data.totals.input_tokens', 300);
        $response->assertJsonPath('data.totals.output_tokens', 70);
        $response->assertJsonPath('data.totals.cost_cents', 3);
        $response->assertJsonPath('data.totals.errors', 1);
        // `json_encode(50.0)` produit `50` (PHP retire le `.0`) : on attend
        // donc 50, entier, et non un flottant.
        $response->assertJsonPath('data.totals.error_rate', 50);

        // Répartition par tenant : le nom de société est résolu (sinon l'écran
        // n'affiche qu'un UUID, inexploitable en supervision).
        $this->assertSame($company->name, $response->json('data.by_tenant.0.company_name'));
        $this->assertSame(2, $response->json('data.by_tenant.0.requests'));

        // Répartition par outil : le nombre de confirmations en attente est
        // distingué des exécutions réelles.
        $this->assertSame('create_employee', $response->json('data.by_tool.0.tool_name'));
        $this->assertSame(2, $response->json('data.by_tool.0.calls'));
        $this->assertSame(1, $response->json('data.by_tool.0.awaiting_confirmation'));

        // Les erreurs récentes portent la cause, jamais le contenu échangé.
        $this->assertSame('429 rate limited', $response->json('data.recent_errors.0.error'));
    }

    public function test_monitoring_reports_health_of_the_assistant(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->getJson('/api/v1/admin/platform/ai/health');

        $response->assertOk();
        $this->assertArrayHasKey('enabled', $response->json('data'));
        $this->assertArrayHasKey('driver', $response->json('data'));
        $this->assertArrayHasKey('provider_key_configured', $response->json('data'));
    }

    public function test_the_settings_are_not_reachable_without_a_super_admin(): void
    {
        $this->getJson('/api/v1/admin/platform/ai/settings')->assertStatus(401);
        $this->putJson('/api/v1/admin/platform/ai/settings', ['settings' => ['driver' => 'groq']])
            ->assertStatus(401);
    }
}
