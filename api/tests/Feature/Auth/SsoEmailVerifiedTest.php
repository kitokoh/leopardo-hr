<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5580 — SSO employé : le claim `email_verified` doit être vrai sur les
 * flux Google (callback web + token mobile) avant tout match par email.
 *
 * Sans cette garde, un IdP (ou un compte Google à l'email non vérifié) peut
 * émettre un identifiant avec un email non vérifié qui matche un employé →
 * prise de contrôle de compte. Le portail client exigeait déjà la preuve
 * (GoogleIdentityVerifier) — parité ici.
 */
class SsoEmailVerifiedTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function seedEmployee(string $email): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => $email,
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        DB::table('public.user_lookups')->insertOrIgnore([
            'email' => $email,
            'company_id' => $company->id,
            'schema_name' => 'shared_tenants',
            'employee_id' => $employee->id,
            'role' => 'employee',
        ]);

        return [$company, $employee];
    }

    private function mockGoogleUser(string $email, bool $emailVerified): void
    {
        $abstractUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn($email);
        $abstractUser->shouldReceive('getName')->andReturn('Google User');
        $abstractUser->shouldReceive('getRaw')->andReturn(['email' => $email, 'email_verified' => $emailVerified]);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider = \Mockery::mock('Laravel\Socialite\Two\GoogleProvider'));
        $provider->shouldReceive('stateless')->andReturn($provider);
        $provider->shouldReceive('user')->andReturn($abstractUser);
        $provider->shouldReceive('userFromToken')->andReturn($abstractUser);
    }

    public function test_google_callback_unverified_email_is_rejected_without_token(): void
    {
        $this->mockGoogleUser('unverified@example.com', emailVerified: false);
        [, $employee] = $this->seedEmployee('unverified@example.com');

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertStatus(401)
            ->assertJsonPath('error', 'EMAIL_NOT_VERIFIED')
            ->assertJsonPath('message', __('errors.EMAIL_NOT_VERIFIED'));
        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertSame(0, $employee->tokens()->count(), 'aucun token ne doit être émis');
    }

    public function test_google_token_unverified_email_is_rejected_without_token(): void
    {
        $this->mockGoogleUser('unverified-mobile@example.com', emailVerified: false);
        [, $employee] = $this->seedEmployee('unverified-mobile@example.com');

        $response = $this->postJson('/api/v1/auth/google/token', ['access_token' => 'fake-token']);

        $response->assertStatus(401)
            ->assertJsonPath('error', 'EMAIL_NOT_VERIFIED');
        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertSame(0, $employee->tokens()->count());
    }

    public function test_google_callback_unverified_email_is_not_autocreated_in_demo_mode(): void
    {
        // Même en DEMO_MODE_ENABLED=true, un email non vérifié ne doit pas
        // créer de compte (sinon l'attaquant auto-crée puis occupe le mail).
        config(['app.demo_mode_enabled' => true]);
        $this->mockGoogleUser('attacker-unverified@example.com', emailVerified: false);

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertStatus(401)
            ->assertJsonPath('error', 'EMAIL_NOT_VERIFIED');
        $this->assertDatabaseMissing('employees', ['email' => 'attacker-unverified@example.com']);
    }

    public function test_google_callback_verified_email_still_gets_token(): void
    {
        // Contre-régression : email vérifié → flux nominal intact.
        $this->mockGoogleUser('verified@example.com', emailVerified: true);
        [, $employee] = $this->seedEmployee('verified@example.com');

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'email'], 'token']);
        $this->assertSame(1, $employee->tokens()->count());
    }
}
