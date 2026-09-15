<?php

namespace Tests\Feature\Training;

use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Database\Seeders\FeaturePlanMatrixSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7432 — la Formation est un module HORIZONTAL et son interrupteur admin
 * doit être **réel**.
 *
 * Constat (issue, vérifié dans le code) : `training` figurait déjà dans
 * `Company::HORIZONTAL_TOOLS` (donc proposé par le provisioning guidé) et dans
 * le sous-menu RH de la barre client, mais il était **absent de
 * `Company::KNOWN_MODULES`** — et de `config/feature-flags.php`.
 *
 * Or `PlatformCompanyFeatureController::update()` reconstruit la carte de
 * features **à partir de `KNOWN_MODULES`** : la clé `training` était donc
 * systématiquement jetée, la fiche entreprise affichait un switch « Centre de
 * Formation » qui ne persistait rien (l'écran mentait), et `GET /auth/me` ne
 * pouvait pas l'exposer.
 *
 * Contrat verrouillé ici (même famille que TravelAgencyModuleRegistryTest #7220
 * et #7235 pour la comptabilité) :
 *   1. `training` est enregistré dans `Company::KNOWN_MODULES` ;
 *   2. il est **fail-closed** par défaut et présent dans la carte résolue
 *      `FeatureFlag::for()` (sinon la plateforme ne peut pas le basculer) ;
 *   3. l'interrupteur admin le persiste réellement (`PATCH
 *      /platform/companies/{id}/features`) ;
 *   4. l'arbitrage de la matrice offre × formation est **explicite** : la
 *      formation reste réservée à l'offre `enterprise` (décision de
 *      tarification, écrite et testée — cf. FeaturePlanMatrixSeeder).
 */
class TrainingModuleRegistryTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // MAT-010 (#5868) — le contrôleur de features audite chaque bascule.
        // L'insertion du recorder est QUALIFIÉE (`public.feature_flag_audits`,
        // la table est publique) : la fixture doit donc l'être aussi, sinon la
        // table atterrit dans `shared_tenants` (search_path de la fixture MVP)
        // et l'audit échoue.
        if (! Schema::hasTable('public.feature_flag_audits')) {
            Schema::create('public.feature_flag_audits', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('flag_key', 80);
                $table->boolean('previous_value');
                $table->boolean('new_value');
                $table->string('source', 40)->default('platform_controller');
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->timestampTz('created_at')->useCurrent();
            });
        }
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_training_is_registered_as_known_module(): void
    {
        $this->assertContains(
            'training',
            Company::KNOWN_MODULES,
            'training doit être dans Company::KNOWN_MODULES pour être activable par la plateforme.',
        );
    }

    public function test_training_is_fail_closed_by_default_and_exposed_in_flag_map(): void
    {
        $company = Company::factory()->create(['features' => ['rh' => true]]);

        $this->assertFalse($company->hasFeature('training'));

        $map = FeatureFlag::for($company);

        $this->assertArrayHasKey('training', $map, 'La clé doit être exposée, sinon la plateforme ne peut pas la basculer.');
        $this->assertFalse($map['training'], 'Fail-closed : un tenant neuf n\'a pas la formation.');
    }

    public function test_platform_admin_switch_really_persists_training_feature(): void
    {
        $company = Company::factory()->create(['features' => ['rh' => true]]);
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin-training@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => ['training' => true],
        ])
            ->assertOk()
            ->assertJsonPath('data.features.training', true);

        // Le vrai contrat : la valeur est écrite en base, pas seulement renvoyée.
        $company->refresh();
        $this->assertTrue($company->hasFeature('training'));
        $this->assertTrue(FeatureFlag::for($company)['training']);

        // Et l'extinction fonctionne aussi (le switch n'est pas un one-way).
        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => ['training' => false],
        ])
            ->assertOk()
            ->assertJsonPath('data.features.training', false);

        $company->refresh();
        $this->assertFalse($company->hasFeature('training'));
    }

    /**
     * Arbitrage #7432 (critère 3) : la formation reste une capacité de l'offre
     * `enterprise`. Le choix est verrouillé par test pour qu'une modification
     * silencieuse de la matrice (tarification) soit détectée.
     */
    public function test_plan_matrix_arbitration_keeps_training_enterprise_only(): void
    {
        if (! Schema::hasTable('feature_plan_matrix')) {
            Schema::create('feature_plan_matrix', function (Blueprint $table): void {
                $table->id();
                $table->string('feature_key', 50);
                $table->string('plan', 30);
                $table->boolean('enabled')->default(false);
                $table->unsignedInteger('limit_value')->nullable();
                $table->timestamps();
                $table->unique(['feature_key', 'plan']);
            });
        }

        $this->seed(FeaturePlanMatrixSeeder::class);

        $rows = DB::table('feature_plan_matrix')
            ->where('feature_key', 'training')
            ->pluck('enabled', 'plan')
            ->map(static fn ($enabled): bool => (bool) $enabled)
            ->all();

        $this->assertSame(
            ['free' => false, 'pilot' => false, 'operations' => false, 'enterprise' => true],
            array_intersect_key($rows, array_flip(['free', 'pilot', 'operations', 'enterprise'])),
            'Arbitrage #7432 : formation réservée à l\'offre enterprise (décision tarifaire explicite).',
        );
    }
}
