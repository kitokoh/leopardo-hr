<?php

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PlatformCompanyFeatureApiTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // MAT-010 (#5868) — le contrôleur audite chaque bascule dans
        // `feature_flag_audits` (table publique de la migration dédiée) :
        // la fixture MVP ne la crée pas, on la matérialise pour ce test.
        if (! Schema::hasTable('feature_flag_audits')) {
            Schema::create('feature_flag_audits', function (Blueprint $table): void {
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

    public function test_super_admin_can_view_and_update_company_feature_flags(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['rh' => true]]);
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->getJson("/api/v1/platform/companies/{$company->id}/features")
            ->assertOk()
            ->assertJsonPath('data.features.rh', true)
            ->assertJsonPath('data.features.finance', false);

        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => [
                'rh' => false,
                'finance' => true,
                'cameras' => true,
                'unknown' => true,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.features.rh', true)
            ->assertJsonPath('data.features.finance', true)
            ->assertJsonPath('data.features.cameras', true);

        $company->refresh();
        $this->assertTrue($company->hasFeature('rh'));
        $this->assertTrue($company->hasFeature('finance'));
        $this->assertArrayNotHasKey('unknown', $company->features ?? []);
    }

    /**
     * #7432 — la Formation est un module CONNU : le switch admin agit
     * réellement (avant, `training` absent de `KNOWN_MODULES` faisait jeter
     * silencieusement la clé => l'écran mentait).
     */
    public function test_training_feature_is_persisted_and_exposed(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['rh' => true]]);
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        // Fail-closed au départ + clé désormais connue du registre.
        $this->getJson("/api/v1/platform/companies/{$company->id}/features")
            ->assertOk()
            ->assertJsonPath('data.features.training', false);

        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => ['training' => true],
        ])
            ->assertOk()
            ->assertJsonPath('data.features.training', true);

        $company->refresh();
        $this->assertTrue($company->hasFeature('training'));

        // Le switch peut aussi l'éteindre.
        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => ['training' => false],
        ])
            ->assertOk()
            ->assertJsonPath('data.features.training', false);

        $company->refresh();
        $this->assertFalse($company->hasFeature('training'));
    }

    /**
     * BOS-012 (#8199) — le moteur de désynchronisation, testé là où il vivait.
     *
     * La console plateforme écrivait `features` sans aligner
     * `metadata.modules`. Or le client (`client-features.ts` →
     * `explicitToolValue`) lit `metadata.modules` **en premier** et « fait
     * AUTORITÉ sur les replis historiques » : un kill switch posé ici restait
     * donc invisible dans l'interface, qui affichait encore la sélection
     * client périmée.
     *
     * Ce test décrit le symptôme observable (l'UI voit encore le module actif
     * après la coupure) et exige la cohérence des deux cartes.
     */
    public function test_updating_features_keeps_the_client_module_view_coherent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'features' => ['rh' => true, 'crm' => true],
        ]);

        // État de départ : le client avait activé CRM (sélection d'inscription).
        $metadata = $company->metadata ?? [];
        $metadata['modules'] = ['crm' => true, 'employees' => true];
        $company->forceFill(['metadata' => $metadata])->save();

        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin-bos012@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        // L'opérateur coupe CRM depuis la console (kill switch d'entitlement).
        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => ['crm' => false],
        ])->assertOk();

        $company->refresh();

        // La SOURCE est bien coupée…
        $this->assertFalse($company->hasFeature('crm'));

        // …et la VUE que lit l'interface doit suivre. Sans l'écrivain unifié,
        // `metadata.modules.crm` restait `true` et le client continuait
        // d'afficher le module actif.
        $this->assertFalse(
            $company->metadata['modules']['crm'] ?? null,
            'La vue client doit suivre le flag plateforme (#8199).'
        );

        // Les clés non-miroir ne sont pas touchées par la dérivation.
        $this->assertTrue(
            $company->metadata['modules']['employees'] ?? null,
            'Les outils sans flag plateforme ne sont pas des dérivations.'
        );
    }

    /**
     * BOS-012 (#8199) — l'invariant central, vérifié sur **toutes** les clés
     * miroir du registre et pas seulement sur celle du payload.
     *
     * ⚠️ Le contrôleur matérialise **tous** les modules connus dans `features`
     * (repli #7432 : une clé absente du payload conserve sa valeur effective).
     * Une fois `features` entièrement déclaré, la vue dérivée doit lui
     * correspondre **clé à clé** — c'est exactement la fin de la
     * désynchronisation que #8199 poursuit. Vérifier une seule clé laisserait
     * passer une dérive sur les douze autres.
     */
    public function test_every_mirror_key_agrees_with_features_after_an_update(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['rh' => true, 'crm' => true]]);

        $metadata = $company->metadata ?? [];
        $metadata['modules'] = ['crm' => true, 'employees' => true];
        $company->forceFill(['metadata' => $metadata])->save();

        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin-bos012b@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => ['cameras' => true],
        ])->assertOk();

        $company->refresh();

        $mirrors = app(\App\Core\Feature\Infrastructure\Services\ModuleRegistryGateway::class)
            ->horizontalMirrors();

        $this->assertNotSame([], $mirrors);

        foreach ($mirrors as $clientKey => $flagKey) {
            // La clé n'est dérivée que si la SOURCE la déclare ; sinon elle doit
            // rester absente (cf. ModuleMirror::derived — on n'invente pas de
            // déclaration pour un tenant qui n'a jamais fait de choix).
            if (! array_key_exists($flagKey, $company->features)) {
                $this->assertArrayNotHasKey(
                    $clientKey,
                    $company->metadata['modules'],
                    "{$clientKey} n'est pas déclaré côté plateforme : la vue ne doit pas l'inventer."
                );

                continue;
            }

            $this->assertSame(
                (bool) $company->features[$flagKey],
                $company->metadata['modules'][$clientKey] ?? null,
                "La vue client de {$clientKey} doit correspondre au flag plateforme {$flagKey}."
            );
        }

        // Le module activé par le payload est bien projeté, et la clé non-miroir
        // n'est pas touchée par la dérivation.
        $this->assertTrue($company->metadata['modules']['cameras'] ?? null);
        $this->assertTrue($company->metadata['modules']['employees'] ?? null);
    }

    /**
     * #7432 — non-régression : une clé ABSENTE du payload conserve la valeur
     * effective du tenant. Sans cette garantie, l'ajout d'un module à
     * `KNOWN_MODULES` éteindrait les features de tout client dont le
     * formulaire n'envoie pas la clé.
     */
    public function test_omitted_modules_keep_their_current_value(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'features' => ['rh' => true, 'cameras' => true, 'training' => true],
        ]);
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin2@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        // Payload partiel : `cameras` et `training` ne sont pas envoyés.
        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => ['finance' => true],
        ])
            ->assertOk()
            ->assertJsonPath('data.features.finance', true)
            ->assertJsonPath('data.features.cameras', true)
            ->assertJsonPath('data.features.training', true);

        $company->refresh();
        $this->assertTrue($company->hasFeature('cameras'));
        $this->assertTrue($company->hasFeature('training'));
        $this->assertTrue($company->hasFeature('finance'));
    }
}
