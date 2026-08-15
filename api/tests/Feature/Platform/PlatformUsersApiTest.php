<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\HR\Domain\Models\UserEmployeeLink;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issues #2229 (QA wave 2026-08-14, T004) + #2269 — API de gestion des
 * utilisateurs plateforme (public.users), réservée au super-admin, sans
 * jamais de suppression physique.
 */
class PlatformUsersApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_index_requires_super_admin(): void
    {
        $this->getJson('/api/v1/platform/users')->assertUnauthorized();

        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/platform/users')->assertUnauthorized();
    }

    public function test_index_paginates_and_searches(): void
    {
        $this->superAdminAuth();

        User::query()->create([
            'first_name' => 'Amina', 'last_name' => 'Benali', 'email' => 'amina.benali@test.com', 'status' => 'active',
        ]);
        User::query()->create([
            'first_name' => 'Karim', 'last_name' => 'Ziani', 'email' => 'karim.ziani@test.com', 'status' => 'active',
        ]);
        User::query()->create([
            'first_name' => 'Sofia', 'last_name' => 'Maroc', 'email' => 'sofia@test.com', 'status' => 'disabled',
        ]);

        $this->getJson('/api/v1/platform/users?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2);

        $this->getJson('/api/v1/platform/users?search=ziani')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'karim.ziani@test.com');

        $this->getJson('/api/v1/platform/users?status=disabled')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'sofia@test.com');

        $this->getJson('/api/v1/platform/users?sort_by=password_hash')
            ->assertStatus(422);
    }

    public function test_index_enriches_linked_company(): void
    {
        $this->superAdminAuth();

        $company = Company::factory()->create(['name' => 'TechCorp Algérie']);
        $user = User::query()->create([
            'first_name' => 'Lina', 'last_name' => 'Haddad', 'email' => 'lina@techcorp.test', 'status' => 'active',
        ]);
        UserEmployeeLink::query()->create([
            'user_id' => $user->id,
            'employee_id' => 1,
            'company_id' => $company->id,
            'status' => 'linked',
            'linked_at' => now(),
        ]);
        User::query()->create([
            'first_name' => 'Seul', 'last_name' => 'Compte', 'email' => 'seul@test.com', 'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/platform/users')->assertOk();

        $byEmail = collect($response->json('data'))->keyBy('email');
        $this->assertSame('TechCorp Algérie', $byEmail['lina@techcorp.test']['company']['name']);
        $this->assertNull($byEmail['seul@test.com']['company']);
    }

    public function test_store_creates_user_with_hashed_password(): void
    {
        $this->superAdminAuth();

        $this->postJson('/api/v1/platform/users', [
            'first_name' => 'Yacine',
            'last_name' => 'Meziane',
            'email' => 'yacine@test.com',
            'password' => 'SecretPass123',
            'preferred_language' => 'ar',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'yacine@test.com')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.preferred_language', 'ar')
            ->assertJsonMissingPath('data.password_hash');

        $user = User::query()->where('email', 'yacine@test.com')->firstOrFail();
        $this->assertTrue(Hash::check('SecretPass123', $user->password_hash));
    }

    public function test_store_rejects_duplicate_email(): void
    {
        $this->superAdminAuth();

        User::query()->create([
            'first_name' => 'Dup', 'last_name' => 'Email', 'email' => 'dup@test.com', 'status' => 'active',
        ]);

        $this->postJson('/api/v1/platform/users', [
            'first_name' => 'Autre',
            'last_name' => 'Email',
            'email' => 'dup@test.com',
        ])->assertStatus(422);
    }

    public function test_update_patches_profile_and_status(): void
    {
        $this->superAdminAuth();

        $user = User::query()->create([
            'first_name' => 'Avant', 'last_name' => 'Nom', 'email' => 'avant@test.com', 'status' => 'active',
        ]);

        $this->patchJson('/api/v1/platform/users/'.$user->id, [
            'first_name' => 'Après',
            'status' => 'suspended',
        ])->assertOk()
            ->assertJsonPath('data.first_name', 'Après')
            ->assertJsonPath('data.status', 'suspended');

        $this->patchJson('/api/v1/platform/users/'.$user->id, ['status' => 'inconnu'])
            ->assertStatus(422);
    }

    public function test_destroy_soft_disables_never_physically_deletes(): void
    {
        $this->superAdminAuth();

        $user = User::query()->create([
            'first_name' => 'Soft', 'last_name' => 'Delete', 'email' => 'soft@test.com', 'status' => 'active',
        ]);

        $this->deleteJson('/api/v1/platform/users/'.$user->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'disabled');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'disabled']);
    }

    public function test_status_actions_activate_deactivate_suspend(): void
    {
        $this->superAdminAuth();

        $user = User::query()->create([
            'first_name' => 'Stat', 'last_name' => 'Us', 'email' => 'stat@test.com', 'status' => 'active',
        ]);

        $this->postJson('/api/v1/platform/users/'.$user->id.'/suspend')->assertJsonPath('data.status', 'suspended');
        $this->postJson('/api/v1/platform/users/'.$user->id.'/deactivate')->assertJsonPath('data.status', 'disabled');
        $this->postJson('/api/v1/platform/users/'.$user->id.'/activate')->assertJsonPath('data.status', 'active');
    }

    public function test_super_admin_cannot_disable_own_account_email(): void
    {
        $admin = $this->superAdminAuth();

        $user = User::query()->create([
            'first_name' => 'Self',
            'last_name' => 'Disable',
            'email' => $admin->email,
            'status' => 'active',
        ]);

        $this->patchJson('/api/v1/platform/users/'.$user->id, ['status' => 'disabled'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'SELF_DISABLE_FORBIDDEN');

        $this->deleteJson('/api/v1/platform/users/'.$user->id)
            ->assertStatus(422);
    }

    public function test_unknown_user_returns_404(): void
    {
        $this->superAdminAuth();

        $this->getJson('/api/v1/platform/users/999999')->assertNotFound();
        $this->patchJson('/api/v1/platform/users/999999', ['first_name' => 'X'])->assertNotFound();
        $this->postJson('/api/v1/platform/users/999999/activate')->assertNotFound();
    }

    private function superAdminAuth(): SuperAdmin
    {
        $admin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'platform-admin@leopardo.test',
            'password_hash' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($admin, ['*'], 'super_admin_api');

        return $admin;
    }
}
