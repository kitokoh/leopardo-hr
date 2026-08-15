<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * QA wave 2026-08-14 — T004 (#2229).
 *
 * CRUD /platform/users (super_admins) : liste paginée/filtrée, création,
 * détail, mise à jour, actions activate/deactivate/suspend, jamais de
 * suppression physique. Auth : guard super_admin_api.
 */
class PlatformUserApiTest extends TestCase
{
    use CreatesMvpSchema;

    protected SuperAdmin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // La fixture MVP ne porte pas la colonne `status` (ajoutée par la
        // migration 2026_08_15_000001) : on l'ajoute localement.
        if (! Schema::hasColumn('super_admins', 'status')) {
            Schema::table('super_admins', function ($table): void {
                $table->string('status', 20)->default('active')->after('email');
            });
        }

        /** @var SuperAdmin $superAdmin */
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Super Admin Test',
            'email' => 'sa-users-test@leopardo-rh.com',
            'password_hash' => bcrypt('secret123'),
        ]);
        $this->superAdmin = $superAdmin;
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function makeUser(string $email = 'other-sa@leopardo-rh.com', string $status = 'active'): SuperAdmin
    {
        /** @var SuperAdmin $user */
        $user = SuperAdmin::query()->create([
            'name' => 'Other Admin',
            'email' => $email,
            'password_hash' => bcrypt('secret123'),
            'status' => $status,
        ]);

        return $user;
    }

    public function test_index_lists_users_with_search_and_status_filter(): void
    {
        $this->makeUser('alice@leopardo-rh.com');
        $this->makeUser('bob@leopardo-rh.com', 'suspended');

        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->getJson('/api/v1/platform/users')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);

        $this->getJson('/api/v1/platform/users?search=alice')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'alice@leopardo-rh.com');

        $this->getJson('/api/v1/platform/users?status=suspended')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'suspended');
    }

    public function test_store_creates_user_with_hashed_password(): void
    {
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $response = $this->postJson('/api/v1/platform/users', [
            'name' => 'New SA',
            'email' => 'new-sa@leopardo-rh.com',
            'password' => 'SuperSecret123!',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'new-sa@leopardo-rh.com');
        $response->assertJsonPath('data.status', 'active');

        $user = SuperAdmin::query()->where('email', 'new-sa@leopardo-rh.com')->firstOrFail();
        $this->assertTrue(Hash::check('SuperSecret123!', (string) $user->password_hash));
    }

    public function test_store_validates_email_uniqueness_and_password_length(): void
    {
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->postJson('/api/v1/platform/users', [
            'name' => 'Dup',
            'email' => 'sa-users-test@leopardo-rh.com',
            'password' => 'SuperSecret123!',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        $this->postJson('/api/v1/platform/users', [
            'name' => 'Weak',
            'email' => 'weak@leopardo-rh.com',
            'password' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_show_returns_user(): void
    {
        $user = $this->makeUser('show-me@leopardo-rh.com');

        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->getJson("/api/v1/platform/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.email', 'show-me@leopardo-rh.com');
    }

    public function test_update_changes_fields_and_rehashes_password(): void
    {
        $user = $this->makeUser('update-me@leopardo-rh.com');

        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->patchJson("/api/v1/platform/users/{$user->id}", [
            'name' => 'Renamed Admin',
            'password' => 'NewSecret456!',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Renamed Admin');

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('NewSecret456!', (string) $fresh->password_hash));
    }

    public function test_destroy_deactivates_without_physical_delete(): void
    {
        $user = $this->makeUser('delete-me@leopardo-rh.com');

        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->deleteJson("/api/v1/platform/users/{$user->id}")
            ->assertStatus(204);

        $this->assertDatabaseHas('super_admins', [
            'id' => $user->id,
            'email' => 'delete-me@leopardo-rh.com',
            'status' => 'deactivated',
        ]);
    }

    public function test_cannot_deactivate_own_account(): void
    {
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->deleteJson("/api/v1/platform/users/{$this->superAdmin->id}")
            ->assertStatus(422);

        $this->postJson("/api/v1/platform/users/{$this->superAdmin->id}/deactivate")
            ->assertStatus(422);
    }

    public function test_actions_activate_deactivate_suspend(): void
    {
        $user = $this->makeUser('actions@leopardo-rh.com');

        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->postJson("/api/v1/platform/users/{$user->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->postJson("/api/v1/platform/users/{$user->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'deactivated');

        $this->postJson("/api/v1/platform/users/{$user->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_requires_super_admin_authentication(): void
    {
        $this->getJson('/api/v1/platform/users')->assertUnauthorized();
    }
}
