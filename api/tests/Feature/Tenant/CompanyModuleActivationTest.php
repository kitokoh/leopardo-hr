<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7322 — auto-activation d'un module HORIZONTAL par le client (tenant).
 *
 * Contrat verrouillé :
 *  1. un manager `principal`/`rh` active un outil horizontal sans intervention
 *     de l'admin plateforme ;
 *  2. les DEUX sources de vérité sont écrites : `metadata.modules[key]=true`
 *     et, quand il existe, le flag plateforme miroir
 *     (`Company::HORIZONTAL_TOOL_FEATURES` — ex. `showcase` →
 *     `company_showcase`) ;
 *  3. allowlist FAIL-CLOSED : une clé inconnue (ou une VERTICALE, hors
 *     périmètre) → 422 et AUCUNE écriture ;
 *  4. RBAC : `principal`/`rh` seulement (comptable → 403, employé → 403) ;
 *  5. idempotence : réactiver un module déjà actif → 200 `already_active`.
 */
class CompanyModuleActivationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

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
     * que le contrôleur (le modèle sous `search_path` tenant pointerait
     * ailleurs, piège documenté).
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

    public function test_principal_activates_a_horizontal_tool_without_platform_flag(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'manager', 'principal');

        $this->postJson('/api/v1/company/modules/employees/activate')
            ->assertOk()
            ->assertJsonPath('data.module', 'employees')
            ->assertJsonPath('data.activated', true)
            ->assertJsonPath('data.already_active', false);

        $persisted = $this->persisted($company);
        $this->assertTrue($persisted['modules']['employees'] ?? null);
        // `employees` n'est pas un flag plateforme : rien ne doit être inventé.
        $this->assertArrayNotHasKey('employees', $persisted['features']);
    }

    public function test_activation_mirrors_the_platform_flag_when_it_exists(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'manager', 'rh');

        $this->postJson('/api/v1/company/modules/showcase/activate')
            ->assertOk()
            ->assertJsonPath('data.activated', true);

        $persisted = $this->persisted($company);
        $this->assertTrue($persisted['modules']['showcase'] ?? null);
        // Miroir `showcase` → `company_showcase` (Company::HORIZONTAL_TOOL_FEATURES).
        $this->assertTrue($persisted['features']['company_showcase'] ?? null);
    }

    public function test_unknown_module_is_rejected_fail_closed_without_write(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'manager', 'principal');

        $this->postJson('/api/v1/company/modules/not_a_module/activate')
            ->assertStatus(422)
            ->assertJsonValidationErrors('module');

        $this->assertSame([], $this->persisted($company)['modules']);
    }

    public function test_vertical_solutions_are_out_of_scope(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'manager', 'principal');

        // Les verticales (restaurant, travel, fuel, éducation) requièrent des
        // seeders/dépendances de pack : activation admin plateforme uniquement.
        foreach (['restaurant', 'travel', 'fuel_station', 'edumanager'] as $vertical) {
            $this->postJson("/api/v1/company/modules/{$vertical}/activate")
                ->assertStatus(422);
        }

        $this->assertSame([], $this->persisted($company)['modules']);
    }

    public function test_comptable_and_employee_cannot_activate(): void
    {
        $company = $this->company();

        $this->actingAsRole($company, 'manager', 'comptable');
        $this->postJson('/api/v1/company/modules/employees/activate')->assertStatus(403);

        $this->actingAsRole($company, 'employee');
        $this->postJson('/api/v1/company/modules/employees/activate')->assertStatus(403);

        $this->assertSame([], $this->persisted($company)['modules']);
    }

    /**
     * #7476 — « Caméras » est un outil HORIZONTAL (besoin transverse), mais il
     * n'est pas utilisable sans capacité : `CameraService::maxCameras()` retombe
     * sur `config('cameras.default_max_cameras')`, 0 par défaut, et toute
     * création répond alors « limite du plan atteinte ». L'activation doit donc
     * poser la capacité par défaut, sinon le client active un module vide.
     */
    public function test_cameras_activation_makes_the_module_usable(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'manager', 'principal');

        $this->postJson('/api/v1/company/modules/cameras/activate')
            ->assertOk()
            ->assertJsonPath('data.module', 'cameras')
            ->assertJsonPath('data.activated', true);

        $persisted = $this->persisted($company);
        $this->assertTrue($persisted['modules']['cameras'] ?? null);
        // Miroir `cameras` → `cameras` (kill switch plateforme).
        $this->assertTrue($persisted['features']['cameras'] ?? null);
        // Capacité posée : sans elle le module serait inutilisable.
        $this->assertSame(
            (int) config('cameras.activation_default_max', 4),
            $persisted['features']['max_cameras'] ?? null,
        );
    }

    /**
     * #7476 — la capacité est OPPOSABLE : une valeur déjà fixée par la console
     * plateforme (12 caméras) n'est jamais écrasée par l'activation, et `null`
     * (= illimité) reste `null`.
     */
    public function test_cameras_activation_never_overwrites_an_existing_capacity(): void
    {
        $explicit = $this->company();
        $explicit->features = ['max_cameras' => 12];
        $explicit->save();
        $this->actingAsRole($explicit, 'manager', 'principal');

        $this->postJson('/api/v1/company/modules/cameras/activate')->assertOk();

        $this->assertSame(12, $this->persisted($explicit)['features']['max_cameras'] ?? null);

        $unlimited = $this->company();
        $unlimited->features = ['max_cameras' => null];
        $unlimited->save();
        $this->actingAsRole($unlimited, 'manager', 'rh');

        $this->postJson('/api/v1/company/modules/cameras/activate')->assertOk();

        $persisted = $this->persisted($unlimited);
        // `null` = illimité (Enterprise) : la clé existe, elle ne doit pas être
        // remplacée par la valeur d'activation.
        $this->assertArrayHasKey('max_cameras', $persisted['features']);
        $this->assertNull($persisted['features']['max_cameras']);
    }

    public function test_activation_is_idempotent(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'manager', 'principal');

        $this->postJson('/api/v1/company/modules/reports/activate')
            ->assertOk()
            ->assertJsonPath('data.activated', true);

        $this->postJson('/api/v1/company/modules/reports/activate')
            ->assertOk()
            ->assertJsonPath('data.activated', false)
            ->assertJsonPath('data.already_active', true);

        $this->assertTrue($this->persisted($company)['modules']['reports'] ?? null);
    }
}
