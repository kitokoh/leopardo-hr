<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #6531 — audit sécurité : l'identité Google (sub) n'était jamais vérifiée
 * ni liée. Le login Google s'authentifiait par email seul → réattribution
 * silencieuse d'un email Workspace après départ, ou compte Google compromis
 * accédant au compte HR.
 *
 * Correctif : `google_id` (sub) lié à la première connexion, mismatch refusé
 * (401 GOOGLE_IDENTITY_MISMATCH), email ambigu (multi-tenants) refusé.
 */
class GoogleIdentityBindingTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function seedEmployee(string $email, ?string $googleId = null): array
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

        if ($googleId !== null) {
            $employee->forceFill(['google_id' => $googleId])->save();
        }

        return [$company, $employee];
    }

    private function mockGoogleUser(string $email, string $sub, bool $viaToken = false): void
    {
        $abstractUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn($email);
        $abstractUser->shouldReceive('getId')->andReturn($sub);
        $abstractUser->shouldReceive('getName')->andReturn('Google User');
        $abstractUser->shouldReceive('offsetGet')->with('given_name')->andReturn('Google');
        $abstractUser->shouldReceive('offsetGet')->with('family_name')->andReturn('User');
        $abstractUser->shouldReceive('getRaw')->andReturn(['email' => $email, 'email_verified' => true, 'sub' => $sub]);

        $provider = \Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('stateless')->andReturn($provider);

        if ($viaToken) {
            $provider->shouldReceive('userFromToken')->andReturn($abstractUser);
        } else {
            $provider->shouldReceive('user')->andReturn($abstractUser);
        }

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_google_callback_binds_google_id_on_first_login(): void
    {
        [$company, $employee] = $this->seedEmployee('first@example.com');
        $this->mockGoogleUser('first@example.com', 'sub-abc-123');

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertOk();
        $response->assertJsonPath('data.id', $employee->id);
        $this->assertSame('sub-abc-123', $employee->fresh()->google_id, 'le sub doit être lié à la première connexion');
    }

    public function test_google_callback_accepts_matching_google_id(): void
    {
        [, $employee] = $this->seedEmployee('match@example.com', 'sub-existing');
        $this->mockGoogleUser('match@example.com', 'sub-existing');

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertOk();
        $response->assertJsonPath('data.id', $employee->id);
        $this->assertSame('sub-existing', $employee->fresh()->google_id);
    }

    public function test_google_callback_rejects_mismatched_google_id(): void
    {
        [, $employee] = $this->seedEmployee('stolen@example.com', 'sub-original');
        $this->mockGoogleUser('stolen@example.com', 'sub-attacker');

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertStatus(401)
            ->assertJsonPath('error', 'GOOGLE_IDENTITY_MISMATCH');
        $this->assertSame('sub-original', $employee->fresh()->google_id, 'le lien d\'origine doit être conservé');
        $this->assertSame(0, $employee->tokens()->count(), 'aucun token ne doit être émis');
    }

    public function test_google_token_rejects_mismatched_google_id(): void
    {
        [, $employee] = $this->seedEmployee('mobile@example.com', 'sub-original');
        $this->mockGoogleUser('mobile@example.com', 'sub-attacker', viaToken: true);

        $response = $this->postJson('/api/v1/auth/google/token', [
            'access_token' => 'fake-token',
            'device_name' => 'test-device',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error', 'GOOGLE_IDENTITY_MISMATCH');
        $this->assertSame(0, $employee->tokens()->count());
    }

    public function test_google_callback_rejects_ambiguous_email_across_tenants(): void
    {
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        Employee::factory()->create([
            'company_id' => $companyA->id,
            'email' => 'dup@example.com',
            'role' => 'employee',
            'status' => 'active',
        ]);
        Employee::factory()->create([
            'company_id' => $companyB->id,
            'email' => 'dup@example.com',
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->mockGoogleUser('dup@example.com', 'sub-dup');

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertStatus(401)
            ->assertJsonPath('error', 'GOOGLE_IDENTITY_MISMATCH');
    }
}
