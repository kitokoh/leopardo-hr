<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #2269 — gestion des utilisateurs plateforme : contrat réel
 * GET/PATCH /admin/users (UsersView/UserDetailView super-admin sans mocks).
 *
 * Lectures ciblées sur le schéma public (users, user_employee_links,
 * companies) + shared_tenants.employees pour les rôles.
 */
class PlatformUsersApiTest extends TestCase
{
    use CreatesMvpSchema;

    protected Company $company;

    protected SuperAdmin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        /** @var Company $company */
        $company = Company::factory()->create(['name' => 'Tenant Test']);
        $this->company = $company;

        /** @var SuperAdmin $superAdmin */
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Super Admin Test',
            'email' => 'sa-admin@leopardo-rh.com',
            'password_hash' => bcrypt('secret123'),
        ]);
        $this->superAdmin = $superAdmin;
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createUser(array $overrides = []): int
    {
        $id = DB::table('users')->insertGetId(array_merge([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont'.uniqid().'@example.com',
            'password_hash' => bcrypt('secret123'),
            'provider' => 'local',
            'preferred_language' => 'fr',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return (int) $id;
    }

    private function linkUserToCompany(int $userId, ?string $companyId = null, string $linkStatus = 'active'): void
    {
        /** @var \App\Core\Auth\Domain\Models\Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $companyId ?? $this->company->id]);

        DB::table('user_employee_links')->insert([
            'user_id' => $userId,
            'employee_id' => $employee->id,
            'company_id' => $companyId ?? $this->company->id,
            'status' => $linkStatus,
            'linked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_admin_users_require_super_admin_auth(): void
    {
        /** @var \App\Core\Auth\Domain\Models\Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
        $this->patchJson('/api/v1/admin/users/1', ['is_active' => false])->assertUnauthorized();
    }

    public function test_index_returns_paginated_users_with_company(): void
    {
        $this->actingAsSuperAdmin();

        $idA = $this->createUser(['first_name' => 'Alice', 'last_name' => 'Martin']);
        $this->createUser(['first_name' => 'Bob', 'last_name' => 'Nguyen']);
        $this->linkUserToCompany($idA, $this->company->id);

        $response = $this->getJson('/api/v1/admin/users?per_page=10');
        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [[
                    'id', 'first_name', 'last_name', 'email', 'status', 'is_active',
                    'company' => ['id', 'name', 'link_status'],
                ]],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        /** @var array<int, array<string, mixed>> $usersData */
        $usersData = $response->json('data') ?? [];
        $alice = collect($usersData)->firstWhere('first_name', 'Alice');
        $this->assertIsArray($alice);
        $this->assertSame('Tenant Test', $alice['company']['name']);
        $this->assertTrue($alice['is_active']);
    }

    public function test_index_search_filters_by_name_and_email(): void
    {
        $this->actingAsSuperAdmin();

        $this->createUser(['first_name' => 'Alice', 'last_name' => 'Martin', 'email' => 'alice.martin@example.com']);
        $this->createUser(['first_name' => 'Bob', 'last_name' => 'Nguyen', 'email' => 'bob.nguyen@example.com']);

        $byName = $this->getJson('/api/v1/admin/users?search=alice');
        $byName->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertSame('Alice', $byName->json('data.0.first_name'));

        $byEmail = $this->getJson('/api/v1/admin/users?search=nguyen@example');
        $byEmail->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertSame('bob.nguyen@example.com', $byEmail->json('data.0.email'));
    }

    public function test_index_sort_allowlist_ignores_unknown_columns(): void
    {
        $this->actingAsSuperAdmin();

        $this->createUser(['first_name' => 'Alpha', 'last_name' => 'Zulu']);
        $this->createUser(['first_name' => 'Beta', 'last_name' => 'Alpha']);

        $sorted = $this->getJson('/api/v1/admin/users?sort_by=email&sort_dir=asc');
        $sorted->assertOk()->assertJsonPath('meta.total', 2);

        // Colonne non allowlistée → repli sur created_at sans erreur SQL.
        $evil = $this->getJson('/api/v1/admin/users?sort_by=(select+1)&sort_dir=desc');
        $evil->assertOk();
    }

    public function test_show_returns_user_detail_with_roles(): void
    {
        $this->actingAsSuperAdmin();

        $id = $this->createUser(['email' => 'detail.user@example.com']);
        $this->linkUserToCompany($id);

        $response = $this->getJson("/api/v1/admin/users/{$id}");
        $response->assertOk()
            ->assertJsonPath('data.email', 'detail.user@example.com')
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'is_active', 'company', 'roles'],
            ]);
        $this->assertCount(1, $response->json('data.roles'));
    }

    public function test_show_unknown_user_returns_404(): void
    {
        $this->actingAsSuperAdmin();
        $this->getJson('/api/v1/admin/users/999999')->assertNotFound();
    }

    public function test_update_toggles_is_active(): void
    {
        $this->actingAsSuperAdmin();

        $id = $this->createUser(['status' => 'active']);
        $this->assertTrue(DB::table('users')->where('id', $id)->value('status') === 'active');

        $disabled = $this->patchJson("/api/v1/admin/users/{$id}", ['is_active' => false]);
        $disabled->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertSame('disabled', DB::table('users')->where('id', $id)->value('status'));

        $enabled = $this->patchJson("/api/v1/admin/users/{$id}", ['is_active' => true]);
        $enabled->assertOk()->assertJsonPath('data.is_active', true);
        $this->assertSame('active', DB::table('users')->where('id', $id)->value('status'));
    }

    public function test_update_self_disable_is_forbidden(): void
    {
        $this->actingAsSuperAdmin();

        // Un utilisateur partage l'email du super-admin courant.
        $id = $this->createUser(['email' => 'sa-admin@leopardo-rh.com']);

        $this->patchJson("/api/v1/admin/users/{$id}", ['is_active' => false])
            ->assertStatus(422)
            ->assertJsonPath('error', 'SELF_DISABLE_FORBIDDEN');

        // Le compte n'a pas été désactivé.
        $this->assertSame('active', DB::table('users')->where('id', $id)->value('status'));
    }

    public function test_update_unknown_user_returns_404(): void
    {
        $this->actingAsSuperAdmin();
        $this->patchJson('/api/v1/admin/users/999999', ['is_active' => false])->assertNotFound();
    }
}
