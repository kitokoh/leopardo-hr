<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Feature\Infrastructure\Services\CompanyFeatureMirror;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8954 — le kill switch plateforme doit être VISIBLE côté client.
 *
 * Le défaut : `client-features.ts::explicitToolValue` lit `metadata.modules`
 * en premier et fait autorité, alors que `PATCH /platform/companies/{id}/features`
 * n'écrivait que `features`. Un opérateur qui coupait une verticale ne changeait
 * donc rien dans l'interface du client — le serveur voyait la coupure, le
 * navigateur non.
 *
 * Les preuves demandées par l'issue sont vérifiées une à une :
 *  1. après un PATCH plateforme, `metadata.modules` correspond à `features`
 *     clé à clé pour toutes les clés miroir du registre ;
 *  2. `hasFeature()` est identique avant/après (parité structurelle) ;
 *  3. les clés non-miroir (`employees`, `attendance`, `absences`, `contracts`,
 *     `payroll`, `reports`, `marketing`) ne sont pas touchées.
 */
class PlatformFeatureMirrorTest extends TestCase
{
    use RefreshTenantDatabase;

    private function actingAsSuperAdmin(): void
    {
        /** @var SuperAdmin $superAdmin */
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin-mirror@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');
    }

    /**
     * Clés miroir du registre (clé client => clé de flag).
     *
     * @return array<string, string>
     */
    private function mirrors(): array
    {
        /** @var CompanyFeatureMirror $mirror */
        $mirror = app(CompanyFeatureMirror::class);
        /** @var \App\Core\Feature\Domain\ModuleRegistry $registry */
        $registry = app(\App\Core\Feature\Domain\ModuleRegistry::class);

        return $registry->horizontalMirrors();
    }

    public function test_platform_patch_aligns_metadata_modules_on_features(): void
    {
        $this->actingAsSuperAdmin();

        /** @var Company $company */
        $company = Company::factory()->create([
            'features' => ['finance' => false, 'cameras' => false],
            // Sélection client périmée : c'est elle qui masquait le kill switch.
            'metadata' => ['modules' => ['accounting' => true, 'cameras' => true]],
        ]);

        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => ['finance' => true, 'cameras' => false],
        ])->assertOk();

        $company->refresh();

        /** @var array<string, mixed> $metadata */
        $metadata = $company->metadata;
        /** @var array<string, mixed> $modules */
        $modules = $metadata['modules'];
        /** @var array<string, bool> $features */
        $features = $company->features;

        foreach ($this->mirrors() as $clientKey => $flagKey) {
            if (! array_key_exists($flagKey, $features)) {
                continue;
            }

            $this->assertArrayHasKey($clientKey, $modules, "clé miroir {$clientKey} absente de metadata.modules");
            $this->assertSame(
                (bool) $features[$flagKey],
                (bool) $modules[$clientKey],
                "metadata.modules.{$clientKey} doit refléter features.{$flagKey}",
            );
        }
    }

    public function test_kill_switch_from_platform_console_is_visible_client_side(): void
    {
        $this->actingAsSuperAdmin();

        /** @var Company $company */
        $company = Company::factory()->create([
            'features' => ['cameras' => true],
            'metadata' => ['modules' => ['cameras' => true]],
        ]);

        // Coupure depuis la console plateforme (LE chemin officiel).
        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => ['cameras' => false],
        ])->assertOk();

        $company->refresh();

        /** @var array<string, mixed> $metadata */
        $metadata = $company->metadata;

        // Avant #8954, cette assertion échouait : l'UI continuait d'afficher
        // le module alors que le serveur l'avait coupé.
        $this->assertFalse((bool) $metadata['modules']['cameras']);
        $this->assertFalse($company->hasFeature('cameras'));
    }

    public function test_has_feature_is_unchanged_by_the_mirror(): void
    {
        $this->actingAsSuperAdmin();

        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['finance' => false, 'leo_ai' => true]]);

        $before = [
            'finance' => $company->hasFeature('finance'),
            'leo_ai' => $company->hasFeature('leo_ai'),
            'rh' => $company->hasFeature('rh'),
        ];

        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => ['finance' => false, 'leo_ai' => true],
        ])->assertOk();

        $company->refresh();

        // Parité structurelle : la double écriture ne doit rien changer à la
        // résolution serveur, seulement à la vue dérivée.
        foreach ($before as $key => $value) {
            $this->assertSame($value, $company->hasFeature($key), "hasFeature({$key}) a changé");
        }
    }

    public function test_non_mirror_horizontal_tools_are_preserved_verbatim(): void
    {
        $this->actingAsSuperAdmin();

        // Outils horizontaux SANS flag plateforme : ils ne vivent QUE dans
        // metadata.modules. Les réécrire depuis `features` les effacerait.
        $clientOnly = [
            'employees' => false,
            'attendance' => true,
            'absences' => false,
            'contracts' => true,
            'payroll' => false,
            'reports' => true,
            'marketing' => false,
        ];

        /** @var Company $company */
        $company = Company::factory()->create([
            'features' => ['finance' => false],
            'metadata' => ['modules' => $clientOnly],
        ]);

        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => ['finance' => true],
        ])->assertOk();

        $company->refresh();

        /** @var array<string, mixed> $metadata */
        $metadata = $company->metadata;
        /** @var array<string, mixed> $modules */
        $modules = $metadata['modules'];

        foreach ($clientOnly as $key => $value) {
            $this->assertArrayHasKey($key, $modules);
            $this->assertSame($value, (bool) $modules[$key], "metadata.modules.{$key} ne doit pas être touchée");
        }
    }

    public function test_mirror_preserves_unrelated_metadata_keys(): void
    {
        $this->actingAsSuperAdmin();

        /** @var Company $company */
        $company = Company::factory()->create([
            'features' => ['cameras' => true],
            'metadata' => [
                'modules' => ['cameras' => true],
                'theme' => 'dark',
                'onboarding_step' => 3,
            ],
        ]);

        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => ['cameras' => false],
        ])->assertOk();

        $company->refresh();

        /** @var array<string, mixed> $metadata */
        $metadata = $company->metadata;

        // Le miroir ne réécrit QUE `metadata.modules` : le reste de `metadata`
        // appartient au tenant et ne doit pas être écrasé.
        $this->assertSame('dark', $metadata['theme']);
        $this->assertSame(3, $metadata['onboarding_step']);
    }

    public function test_is_aligned_predicate_reports_drift(): void
    {
        /** @var CompanyFeatureMirror $mirror */
        $mirror = app(CompanyFeatureMirror::class);

        /** @var Company $drifted */
        $drifted = Company::factory()->create([
            'features' => ['cameras' => false],
            'metadata' => ['modules' => ['cameras' => true]],
        ]);

        /** @var Company $aligned */
        $aligned = Company::factory()->create([
            'features' => ['cameras' => false],
            'metadata' => ['modules' => ['cameras' => false]],
        ]);

        /** @var array<string, bool> $driftedFeatures */
        $driftedFeatures = $drifted->features;
        /** @var array<string, bool> $alignedFeatures */
        $alignedFeatures = $aligned->features;

        $this->assertFalse($mirror->isAligned($drifted, $driftedFeatures));
        $this->assertTrue($mirror->isAligned($aligned, $alignedFeatures));

        // Après application, l'écart est résorbé.
        $mirror->apply($drifted, $driftedFeatures);
        $drifted->save();
        $drifted->refresh();

        /** @var array<string, bool> $refreshed */
        $refreshed = $drifted->features;
        $this->assertTrue($mirror->isAligned($drifted, $refreshed));
    }

    public function test_mirror_does_not_invent_keys_for_unwritten_flags(): void
    {
        /** @var CompanyFeatureMirror $mirror */
        $mirror = app(CompanyFeatureMirror::class);

        /** @var Company $company */
        $company = Company::factory()->create([
            'features' => [],
            'metadata' => ['modules' => ['cameras' => true]],
        ]);

        // `features` ne contient AUCUN flag : une clé non écrite par l'appelant
        // n'est pas une clé à `false`, elle ne doit donc pas être forcée.
        $mirror->apply($company, []);

        /** @var array<string, mixed> $metadata */
        $metadata = $company->metadata;
        $this->assertTrue((bool) $metadata['modules']['cameras']);

        // Et le miroir n'a rien inventé dans la carte brute non plus :
        // `features` reste vide (les défauts sont résolus à la lecture par
        // FeatureFlag, jamais matérialisés dans la colonne).
        $this->assertSame([], $company->features);
    }
}
