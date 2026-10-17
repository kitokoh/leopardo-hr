<?php

declare(strict_types=1);

namespace Tests\Feature\BusinessOs;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-T-01 / #8624 — critères d'acceptation au niveau du CONTRAT HTTP.
 *
 * `SitesFirstClassTest` verrouille les invariants du modèle et de la base.
 * Ce fichier verrouille ce que l'API répond réellement, parce que c'est là que
 * se produisent les fuites multi-tenant : un `Route::get('/sites/{site}')`
 * sans scope résout l'ID dans TOUS les tenants si la liaison de route n'est pas
 * tenant-scopée.
 */
class SiteApiContractTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    /**
     * Bind le tenant courant comme le fait `TenantMiddleware` en production.
     * Sans ce binding, le global scope `company` est inerte (#3727) et un test
     * d'isolation multi-tenant passerait pour de mauvaises raisons.
     */
    private function asTenant(Company $company, Employee $employee): void
    {
        // Ordre imposé : on purge d'abord le garde en cache (sinon le second
        // acteur d'un même test continue de résoudre le premier), PUIS on
        // authentifie, PUIS on pose le tenant courant comme le fait
        // `TenantMiddleware` en production. Sans ce binding, le global scope
        // `company` est inerte (#3727) et un test d'isolation passerait pour
        // de mauvaises raisons.
        auth()->forgetGuards();
        Sanctum::actingAs($employee);
        app()->instance('current_company', $company);
    }

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create([
            'schema_name' => 'shared_tenants',
            'country' => 'DZ',
            'currency' => 'DZD',
            'timezone' => 'UTC',
        ]);
        $this->company = $company;

        $this->manager = $this->employee($company, 'manager.sites@a.test', 'manager', 'principal');
    }

    private function employee(
        Company $company,
        string $email,
        ?string $role,
        ?string $managerRole,
    ): Employee {
        $employee = new Employee(['email' => $email]);
        $employee->forceFill([
            'password_hash' => Hash::make('password123'),
            'first_name' => 'Test',
            'last_name' => 'SITE',
            'salary_base' => 60000,
        ])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ])->save();

        /** @var Employee $employee */
        return $employee;
    }

    /**
     * @return array<string, string>
     */
    private function payload(string $code = 'SUD-01'): array
    {
        return [
            'name' => 'Restaurant du Sud',
            'code' => $code,
            'site_type' => 'restaurant',
            'address' => '12 rue des Dattes',
            'timezone' => 'Africa/Algiers',
            'currency' => 'DZD',
        ];
    }

    public function test_manager_creates_a_site_and_receives_its_code_and_status(): void
    {
        $this->asTenant($this->company, $this->manager);

        $response = $this->postJson('/api/v1/sites', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.code', 'SUD-01')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.site_type', 'restaurant')
            ->assertJsonPath('data.timezone', 'Africa/Algiers');

        $this->assertSame(1, Site::query()->count());
    }

    public function test_code_is_normalized_to_uppercase_on_write(): void
    {
        $this->asTenant($this->company, $this->manager);

        $this->postJson('/api/v1/sites', $this->payload('nord-02'))
            ->assertCreated()
            ->assertJsonPath('data.code', 'NORD-02');
    }

    public function test_duplicate_code_within_tenant_returns_422(): void
    {
        $this->asTenant($this->company, $this->manager);

        $this->postJson('/api/v1/sites', $this->payload('DOUBLE'))->assertCreated();
        $this->postJson('/api/v1/sites', $this->payload('DOUBLE'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->assertSame(1, Site::query()->where('code', 'DOUBLE')->count());
    }

    public function test_same_code_is_accepted_in_two_different_tenants(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create([
            'schema_name' => 'shared_tenants',
            'country' => 'MA',
            'currency' => 'MAD',
            'timezone' => 'UTC',
        ]);
        $otherManager = $this->employee($other, 'manager.other@b.test', 'manager', 'principal');

        $this->asTenant($this->company, $this->manager);
        $this->postJson('/api/v1/sites', $this->payload('PRINCIPAL'))->assertCreated();

        $this->asTenant($other, $otherManager);
        $this->postJson('/api/v1/sites', $this->payload('PRINCIPAL'))->assertCreated();

        $this->assertSame(2, Site::withoutGlobalScopes()->where('code', 'PRINCIPAL')->count());
    }

    public function test_cross_tenant_show_is_not_found(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create([
            'schema_name' => 'shared_tenants',
            'country' => 'MA',
            'currency' => 'MAD',
            'timezone' => 'UTC',
        ]);
        $otherManager = $this->employee($other, 'manager.cross@b.test', 'manager', 'principal');

        $this->asTenant($other, $otherManager);
        $foreign = $this->postJson('/api/v1/sites', $this->payload('FOREIGN'))->json('data.id');

        // Un manager du tenant A ne doit JAMAIS pouvoir lire l'établissement
        // du tenant B par son identifiant.
        $this->asTenant($this->company, $this->manager);
        $this->getJson("/api/v1/sites/{$foreign}")->assertNotFound();
    }

    public function test_index_is_scoped_to_the_current_tenant(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create([
            'schema_name' => 'shared_tenants',
            'country' => 'MA',
            'currency' => 'MAD',
            'timezone' => 'UTC',
        ]);
        $otherManager = $this->employee($other, 'manager.index@b.test', 'manager', 'principal');

        $this->asTenant($other, $otherManager);
        $this->postJson('/api/v1/sites', $this->payload('CHEZ-B'))->assertCreated();

        $this->asTenant($this->company, $this->manager);
        $this->postJson('/api/v1/sites', $this->payload('CHEZ-A'))->assertCreated();

        $response = $this->getJson('/api/v1/sites')->assertOk();

        $codes = array_column($response->json('data'), 'code');
        $this->assertContains('CHEZ-A', $codes);
        $this->assertNotContains('CHEZ-B', $codes);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $this->asTenant($this->company, $this->manager);

        $this->postJson('/api/v1/sites', [...$this->payload('STATUT'), 'status' => 'demolished'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_migration_up_and_down_are_replayable(): void
    {
        // Critère #8624 : `down()` supprime colonnes et index, `up()` rejoue.
        // Un `migrate:rollback` suivi d'un `migrate` doit rendre la table à
        // l'identique — sans toucher aux lignes existantes.
        $site = Site::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Établissement historique',
            'code' => 'HIST',
        ]);

        $migration = require base_path(
            'database/migrations/tenant/2026_10_17_120000_8624_sites_first_class.php'
        );

        $migration->down();
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('sites', 'site_type'),
            'down() doit retirer les colonnes ajoutées.'
        );

        $migration->up();
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn('sites', 'site_type'),
            'up() doit être rejouable après un down().'
        );

        // Les données métier ne sont jamais supprimées par down().
        $this->assertSame(
            'Établissement historique',
            Site::withoutGlobalScopes()->find($site->id)?->name
        );
    }
}
