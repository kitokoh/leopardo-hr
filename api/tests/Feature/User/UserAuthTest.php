<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Core\Auth\Domain\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Contrat du module `user` (comptes ordinaires sans entreprise).
 *
 * Seul module de la plateforme sans aucun test Feature avant la vague
 * QA 2026-08-14 (12 routes : /user/register, /user/login, /user/google-signin,
 * /user/me, /user/profile, /user/change-password, /user/logout,
 * /user/company-requests, /user/employee-links, /employees/link-user).
 */
class UserAuthTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_register_creates_user_and_returns_bearer_token(): void
    {
        $response = $this->postJson('/api/v1/user/register', [
            'first_name' => 'Amine',
            'last_name' => 'Bouzid',
            'email' => 'amine.bouzid@example.com',
            'password' => 'secret-pass-123',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.email', 'amine.bouzid@example.com')
            ->assertJsonPath('data.account_type', 'user')
            ->assertJsonStructure(['token', 'token_type']);

        $this->assertDatabaseHas('users', ['email' => 'amine.bouzid@example.com']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $this->postJson('/api/v1/user/register', [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'dup@example.com',
            'password' => 'secret-pass-123',
        ])->assertStatus(422);
    }

    public function test_register_rejects_weak_password(): void
    {
        $this->postJson('/api/v1/user/register', [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'weak@example.com',
            'password' => 'short',
        ])->assertStatus(422);
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password_hash' => Hash::make('secret-pass-123'),
        ]);

        $this->postJson('/api/v1/user/login', [
            'email' => 'login@example.com',
            'password' => 'secret-pass-123',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'login@example.com')
            ->assertJsonStructure(['token', 'token_type']);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password_hash' => Hash::make('secret-pass-123'),
        ]);

        $this->postJson('/api/v1/user/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ])
            ->assertStatus(401)
            ->assertJsonPath('error', 'INVALID_CREDENTIALS');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/user/me')->assertStatus(401);

        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user, [], 'user_api');

        $this->getJson('/api/v1/user/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'me@example.com')
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_update_profile_persists_changes(): void
    {
        $user = User::factory()->create(['preferred_language' => 'fr']);
        Sanctum::actingAs($user, [], 'user_api');

        $this->patchJson('/api/v1/user/profile', [
            'first_name' => 'Nouveau',
            'phone' => '+213555010203',
            'preferred_language' => 'ar',
        ])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Nouveau');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Nouveau',
            'preferred_language' => 'ar',
        ]);
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password_hash' => Hash::make('old-pass-123')]);
        Sanctum::actingAs($user, [], 'user_api');

        $this->postJson('/api/v1/user/change-password', [
            'current_password' => 'wrong-password',
            'new_password' => 'new-pass-123',
            'new_password_confirmation' => 'new-pass-123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_CURRENT_PASSWORD');
    }

    public function test_change_password_updates_hash(): void
    {
        $user = User::factory()->create(['password_hash' => Hash::make('old-pass-123')]);
        Sanctum::actingAs($user, [], 'user_api');

        $this->postJson('/api/v1/user/change-password', [
            'current_password' => 'old-pass-123',
            'new_password' => 'new-pass-123',
            'new_password_confirmation' => 'new-pass-123',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->assertTrue(Hash::check('new-pass-123', (string) $user->fresh()?->password_hash));
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/user/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_employee_links_requires_authenticated_user(): void
    {
        $this->getJson('/api/v1/user/employee-links')->assertStatus(401);

        $user = User::factory()->create();
        Sanctum::actingAs($user, [], 'user_api');

        $this->getJson('/api/v1/user/employee-links')->assertOk();
    }

    public function test_company_requests_require_authenticated_user(): void
    {
        $this->getJson('/api/v1/user/company-requests')->assertStatus(401);

        $user = User::factory()->create();
        Sanctum::actingAs($user, [], 'user_api');

        $this->getJson('/api/v1/user/company-requests')->assertOk();
    }
}
