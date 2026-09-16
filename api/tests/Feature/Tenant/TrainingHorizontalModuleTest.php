<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Application\Services\HorizontalToolSelection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7432 — la Formation est un module HORIZONTAL de bout en bout.
 *
 * Constat de l'issue : `training` figurait dans `Company::HORIZONTAL_TOOLS`,
 * `SELF_ACTIVATABLE_MODULE_KEYS` et le sous-menu RH, mais était ABSENT de
 * `Company::KNOWN_MODULES` et du registre `config/feature-flags.php`. Côté
 * tenant, cela voulait dire :
 *   - `FeatureFlag::for()` (donc `/auth/me`) ignorait la clé — le front la
 *     croyait inexistante ;
 *   - l'auto-activation (`POST /company/modules/training/activate`) écrivait
 *     `metadata.modules.training` sans le flag plateforme miroir.
 *
 * Contrat verrouillé ici :
 *  1. `training` est un module CONNU et un outil horizontal avec flag miroir ;
 *  2. `GET /auth/me` renvoie `features.training` pour le tenant courant ;
 *  3. l'auto-activation client écrit les DEUX sources de vérité ;
 *  4. décision « solo » (#7423) : la Formation reste un outil d'ÉQUIPE — hors
 *     du plancher d'accès garanti à un indépendant.
 */
class TrainingHorizontalModuleTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function company(array $attributes = []): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(array_merge([
            'country' => 'DZ',
            'currency' => 'DZD',
        ], $attributes));

        return $company;
    }

    private function actingAsRole(Company $company, string $role, ?string $managerRole = null): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    /**
     * Relecture de `public.companies` par requête QUALIFIÉE — même précaution
     * que `CompanyModuleController` (le modèle sous `search_path` tenant
     * pointerait ailleurs, piège documenté #7322).
     *
     * @return array{modules: array<string, mixed>, features: array<string, mixed>}
     */
    private function persisted(Company $company): array
    {
        $table = DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
        $row = DB::table($table)->where('id', $company->id)->first();

        return [
            'modules' => json_decode((string) ($row->metadata ?? '{}'), true)['modules'] ?? [],
            'features' => json_decode((string) ($row->features ?? '{}'), true),
        ];
    }

    // ── 1. Module connu + flag enregistré ───────────────────────────────────

    public function test_training_is_a_known_horizontal_module_with_a_platform_flag(): void
    {
        self::assertContains('training', Company::KNOWN_MODULES);
        self::assertContains('training', Company::HORIZONTAL_TOOLS);
        self::assertSame(
            'training',
            Company::HORIZONTAL_TOOL_FEATURES['training'],
            'la Formation est horizontale ET possède un flag plateforme miroir',
        );

        // Le registre versionné connaît la clé : sans entrée, `FeatureFlag::for()`
        // la résoudrait en `false` fail-closed et /auth/me ne l'exposerait pas.
        $resolved = FeatureFlag::for(null);
        self::assertArrayHasKey('training', $resolved);
        self::assertFalse($resolved['training'], 'défaut fail-closed (le module s\'active par tenant)');
    }

    // ── 2. /auth/me expose le flag du tenant ────────────────────────────────

    public function test_auth_me_exposes_the_training_flag(): void
    {
        $company = $this->company(['features' => ['rh' => true, 'training' => true]]);
        $this->actingAsRole($company, 'manager', 'principal');

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.features.training', true);
    }

    public function test_auth_me_reports_training_disabled_when_the_flag_is_off(): void
    {
        $company = $this->company(['features' => ['rh' => true]]);
        $this->actingAsRole($company, 'manager', 'principal');

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.features.training', false);
    }

    // ── 3. Auto-activation : les deux sources de vérité ─────────────────────

    public function test_self_activation_mirrors_the_training_flag(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'manager', 'rh');

        $this->postJson('/api/v1/company/modules/training/activate')
            ->assertOk()
            ->assertJsonPath('data.module', 'training')
            ->assertJsonPath('data.activated', true)
            ->assertJsonPath('data.already_active', false);

        $persisted = $this->persisted($company);

        // Sélection client (lue par `client-features.ts`)…
        self::assertTrue($persisted['modules']['training'] ?? null);
        // …ET flag plateforme miroir (`HORIZONTAL_TOOL_FEATURES`), désormais
        // exposé par /auth/me — c'était l'incohérence #7432.
        self::assertTrue($persisted['features']['training'] ?? null);
    }

    // ── 4. Décision « solo » (#7423) : outil d'équipe ───────────────────────

    public function test_training_stays_a_team_tool_outside_the_solo_floor(): void
    {
        // Décision produit actée par #7432 et cohérente avec le plancher
        // d'accès de #7423 : la Formation s'adresse à l'équipe (un
        // indépendant se forme seul, il n'a pas de « Centre de Formation » à
        // piloter) ⇒ `training` reste dans `TEAM_TOOLS` et n'entre JAMAIS
        // dans le plancher garanti `SOLO_FLOOR_MODULES` (le cas échéant).
        self::assertContains('training', Company::TEAM_TOOLS);

        $reflection = new \ReflectionClass(Company::class);

        // #7423 a nommé son plancher `SOLO_FLOOR_TOOLS` (commit concurrent sur
        // cette branche) ; on verrouille l'invariant quel que soit le nom
        // retenu, pour qu'une évolution future ne rouvre jamais la Formation
        // aux indépendants sans casser ce test.
        foreach (['SOLO_FLOOR_TOOLS', 'SOLO_FLOOR_MODULES'] as $constant) {
            if ($reflection->hasConstant($constant)) {
                self::assertNotContains(
                    'training',
                    (array) $reflection->getConstant($constant),
                    "la Formation ne doit pas être ouverte aux indépendants (#7423) — constante {$constant}",
                );
            }
        }
    }

    public function test_solo_provisioning_does_not_unlock_training(): void
    {
        // Même si un solo coche « Formation » à l'inscription, la règle
        // serveur reprend la main : `TEAM_TOOLS` est forcé à `false`.
        $selection = app(HorizontalToolSelection::class)->resolve(
            ['training', 'reports'],
            Company::TYPE_SOLO,
        );

        self::assertIsArray($selection);
        self::assertFalse($selection['training'], 'la Formation reste un outil d\'équipe (#7423)');
        self::assertTrue($selection['reports']);
    }
}
