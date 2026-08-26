<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\TotpService;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanySetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5579 — Bypass 2FA sur les flux SSO Google.
 *
 * La garde `two_fa_enabled_at` / `requiresMfa()` n'existait que sur login() :
 * Google callback et Google token émettaient un token Sanctum sans jamais la
 * consulter. Miroir de TwoFactorAuthTest appliqué aux deux flux Google :
 * compte enrôlé → challenge TOTP (aucun token) ; politique tenant → 403
 * TWO_FACTOR_REQUIRED (aucun token).
 */
class SsoTwoFactorGuardTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function seedEmployee(string $email, string $role = 'ordinary', ?string $managerRole = null, bool $with2fa = false): array
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => $email,
            'password_hash' => Hash::make('password123'),
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        if ($with2fa) {
            $secret = (new TotpService)->generateSecret();
            $employee->forceFill([
                'two_fa_secret' => $secret,
                'two_fa_enabled_at' => now(),
                'two_fa_recovery_codes' => [],
            ])->save();
        }

        DB::table('public.user_lookups')->insertOrIgnore([
            'email' => $email,
            'company_id' => $company->id,
            'schema_name' => 'shared_tenants',
            'employee_id' => $employee->id,
            'role' => $role,
        ]);

        return [$company, $employee];
    }

    private function mockGoogleUser(string $email): void
    {
        $abstractUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn($email);
        $abstractUser->shouldReceive('getName')->andReturn('Google User');
        $abstractUser->shouldReceive('offsetGet')->with('given_name')->andReturn('Google');
        $abstractUser->shouldReceive('offsetGet')->with('family_name')->andReturn('User');
        $abstractUser->shouldReceive('offsetGet')->with('email_verified')->andReturn(true);
        $abstractUser->shouldReceive('getRaw')->andReturn(['email_verified' => true, 'email' => $email]);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider = \Mockery::mock('Laravel\Socialite\Two\GoogleProvider'));
        $provider->shouldReceive('stateless')->andReturn($provider);
        $provider->shouldReceive('user')->andReturn($abstractUser);
        $provider->shouldReceive('userFromToken')->andReturn($abstractUser);
    }

    public function test_google_token_with_2fa_enrolled_returns_challenge_and_no_token(): void
    {
        $this->mockGoogleUser('2fa-google@example.com');
        [, $employee] = $this->seedEmployee('2fa-google@example.com', with2fa: true);

        $response = $this->postJson('/api/v1/auth/google/token', ['access_token' => 'fake-token']);

        $response->assertStatus(200)
            ->assertJsonPath('mfa_challenge', true)
            ->assertJsonStructure(['mfa_challenge_token', 'mfa_challenge_expires_in']);
        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertSame(0, $employee->tokens()->count(), 'aucun token Sanctum ne doit être émis');
    }

    public function test_google_token_with_mfa_policy_blocks_without_token(): void
    {
        $this->mockGoogleUser('rh-google@example.com');
        [$company, $employee] = $this->seedEmployee('rh-google@example.com', role: 'manager', managerRole: 'rh');

        CompanySetting::query()->create([
            'key' => 'mfa_required_roles',
            'value' => 'rh,principal',
            'value_type' => 'string',
        ]);

        $response = $this->postJson('/api/v1/auth/google/token', ['access_token' => 'fake-token']);

        $response->assertStatus(403)
            ->assertJsonPath('error', 'TWO_FACTOR_REQUIRED');
        $this->assertSame(0, $employee->tokens()->count());
    }

    public function test_google_callback_with_2fa_enrolled_returns_challenge_and_no_token(): void
    {
        $this->mockGoogleUser('2fa-cb@example.com');
        [, $employee] = $this->seedEmployee('2fa-cb@example.com', with2fa: true);

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertStatus(200)
            ->assertJsonPath('mfa_challenge', true)
            ->assertJsonStructure(['mfa_challenge_token', 'mfa_challenge_expires_in']);
        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertSame(0, $employee->tokens()->count());
    }

    public function test_google_callback_with_mfa_policy_blocks_without_token(): void
    {
        $this->mockGoogleUser('rh-cb@example.com');
        [$company, $employee] = $this->seedEmployee('rh-cb@example.com', role: 'manager', managerRole: 'rh');

        CompanySetting::query()->create([
            'key' => 'mfa_required_roles',
            'value' => 'rh,principal',
            'value_type' => 'string',
        ]);

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertStatus(403)
            ->assertJsonPath('error', 'TWO_FACTOR_REQUIRED');
        $this->assertSame(0, $employee->tokens()->count());
    }

    public function test_google_token_without_2fa_still_issues_token(): void
    {
        // Contre-régression : la garde ne doit pas casser le flux Google nominal.
        $this->mockGoogleUser('plain-google@example.com');
        $this->seedEmployee('plain-google@example.com');

        $response = $this->postJson('/api/v1/auth/google/token', ['access_token' => 'fake-token']);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'email'], 'token']);
    }
}
