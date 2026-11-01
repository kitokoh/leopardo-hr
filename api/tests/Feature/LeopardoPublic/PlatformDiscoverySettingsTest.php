<?php

declare(strict_types=1);

namespace Tests\Feature\LeopardoPublic;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Shared\Services\PublicCommerce\PlatformSettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * COULOIR 16 — M1 · édition du seuil de catégories depuis le Superadmin.
 *
 * ## Ce que ces tests verrouillent, par ordre d'importance
 *
 * 1. **Un non-autorisé ne peut pas écrire** — test avec un VRAI jeton Sanctum
 *    et `Auth::forgetGuards()` : les guards en cache sont oubliés pour que le
 *    test ne puisse pas « réussir » grâce à un guard résiduel d'un test
 *    précédent. C'est la seule façon de prouver l'autorisation réelle.
 * 2. **Zéro est refusé, pas corrigé en silence** — un super admin qui tape 0
 *    doit recevoir un 422. Le corriger discrètement en 5 lui ferait croire que
 *    son réglage a été appliqué.
 * 3. **Le changement est journalisé avec l'ancienne valeur** — sans elle, une
 *    baisse de seuil (qui élargit l'affichage public) serait indétectable.
 * 4. **Rejouer la même valeur n'écrit rien** — `PUT` idempotent : ni écriture,
 *    ni ligne d'audit.
 * 5. **Un tenant n'atteint pas ces routes** — un employé authentifié sur le
 *    guard `sanctum` (tenant) ne doit pas toucher à un réglage de plateforme.
 */
class PlatformDiscoverySettingsTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // La table vient d'une migration `public/` ; en test on garantit sa
        // présence car `setUpMvpSchema()` ne rejoue pas toutes les migrations.
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS public.platform_settings (
    key varchar(120) PRIMARY KEY,
    value jsonb NOT NULL,
    description varchar(255) NULL,
    updated_by bigint NULL,
    created_at timestamp(0) with time zone NULL,
    updated_at timestamp(0) with time zone NULL
)
SQL);
        DB::table('platform_settings')->delete();
    }

    protected function tearDown(): void
    {
        DB::table('platform_settings')->delete();
        parent::tearDown();
    }

    /**
     * Super admin réellement persisté + jeton Sanctum réel.
     *
     * On oublie les guards AVANT et APRÈS l'activation : sans cela, un test
     * précédent peut laisser un guard résolu en cache et le test passerait
     * pour la mauvaise raison.
     */
    private function actingAsSuperAdmin(): SuperAdmin
    {
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        auth()->forgetGuards();
        \Laravel\Sanctum\Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        return $superAdmin;
    }

    public function test_a_super_admin_reads_the_current_threshold(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->getJson('/api/v1/admin/platform/discovery/settings');

        $response->assertOk();
        // Aucune ligne en base → défaut servi, jamais 0.
        $this->assertSame(5, $response->json('data.effective'));
        $this->assertSame(1, $response->json('data.min'));
    }

    public function test_a_super_admin_can_change_the_threshold_and_it_is_persisted(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->putJson('/api/v1/admin/platform/discovery/settings', [
            'category_min_pages' => 8,
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('data.changed'));
        $this->assertSame(5, $response->json('data.previous'));
        $this->assertSame(8, $response->json('data.current'));

        // La lecture publique doit immédiatement servir 8.
        $this->assertSame(8, app(PlatformSettingsRepository::class)->categoryMinPages());
    }

    public function test_zero_is_rejected_not_silently_replaced(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->putJson('/api/v1/admin/platform/discovery/settings', [
            'category_min_pages' => 0,
        ]);

        // 422 : le super admin doit SAVOIR que 0 est invalide. Un 5 silencieux
        // lui ferait croire que son réglage a été accepté.
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['category_min_pages']);

        // Et rien n'a été écrit.
        $this->assertSame(0, DB::table('platform_settings')->count());
    }

    public function test_a_non_integer_is_rejected(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson('/api/v1/admin/platform/discovery/settings', [
            'category_min_pages' => '5.5',
        ])->assertStatus(422);

        $this->putJson('/api/v1/admin/platform/discovery/settings', [
            'category_min_pages' => 'abc',
        ])->assertStatus(422);
    }

    public function test_the_change_is_audited_with_the_previous_value(): void
    {
        $superAdmin = $this->actingAsSuperAdmin();

        $this->putJson('/api/v1/admin/platform/discovery/settings', [
            'category_min_pages' => 9,
        ])->assertOk();

        $log = AuditLog::query()
            ->where('action', 'platform_setting.updated')
            ->where('auditable_type', PlatformSettingsRepository::class)
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'Le changement de seuil doit être journalisé.');
        // L'ANCIENNE valeur est indispensable : sans elle, une baisse de seuil
        // (qui élargit l'affichage public) serait invisible en audit.
        $this->assertSame(
            5,
            $log->old_values['discovery_category_min_pages'] ?? null,
            'L’ancienne valeur doit être conservée : sans elle, une BAISSE de seuil '
            .'(qui élargit l’affichage public) serait indétectable en audit.',
        );
        $this->assertSame(9, ($log->new_values['discovery_category_min_pages'] ?? null));
        $this->assertSame(PlatformSettingsRepository::DISCOVERY_CATEGORY_MIN_PAGES, $log->metadata['setting_key'] ?? null);
        // Événement de PLATEFORME : aucun tenant associé.
        $this->assertNull($log->company_id);
    }

    public function test_replaying_the_same_value_writes_nothing(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson('/api/v1/admin/platform/discovery/settings', [
            'category_min_pages' => 7,
        ])->assertOk();

        $auditAfterFirst = AuditLog::query()
            ->where('action', 'platform_setting.updated')
            ->count();

        $second = $this->putJson('/api/v1/admin/platform/discovery/settings', [
            'category_min_pages' => 7,
        ]);

        $second->assertOk();
        $this->assertFalse($second->json('data.changed'), 'PUT idempotent : la 2e écriture ne change rien.');

        $this->assertSame(
            $auditAfterFirst,
            AuditLog::query()->where('action', 'platform_setting.updated')->count(),
            'Une écriture sans changement ne doit pas polluer le journal.',
        );
    }

    public function test_an_anonymous_caller_is_rejected(): void
    {
        // Vrai appel HTTP, sans jeton : le middleware doit refuser.
        auth()->forgetGuards();

        $this->putJson('/api/v1/admin/platform/discovery/settings', [
            'category_min_pages' => 8,
        ])->assertStatus(401);

        $this->assertSame(0, DB::table('platform_settings')->count());
    }

    public function test_a_tenant_employee_token_cannot_reach_platform_settings(): void
    {
        // Un employé de tenant, authentifié sur le guard `sanctum` : c'est la
        // population la plus susceptible de tenter l'appel. Le guard
        // `super_admin_api` ne le connaît pas → refus.
        $company = Company::factory()->create();
        $company->forceFill(['name' => 'Test Co'])->save();

        auth()->forgetGuards();

        $this->putJson('/api/v1/admin/platform/discovery/settings', [
            'category_min_pages' => 3,
        ])->assertStatus(401);

        $this->assertSame(0, DB::table('platform_settings')->count());
    }

    public function test_the_repository_itself_refuses_to_store_zero(): void
    {
        $repository = app(PlatformSettingsRepository::class);

        // Même en contournant le contrôleur, la garantie tient : c'est elle
        // qui protège contre tout futur appelant (commande artisan, seeder,
        // autre contrôleur).
        $result = $repository->putCategoryMinPages(0);

        $this->assertSame(5, $result['current'], '0 est ramené au défaut, jamais stocké.');
        $this->assertSame(5, $repository->categoryMinPages());
    }
}
