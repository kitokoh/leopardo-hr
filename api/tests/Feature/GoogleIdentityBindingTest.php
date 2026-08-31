<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * audit(securite) #6531 — la connexion Google doit lier le sub Google
 * (google_id) à l'employé à la première connexion, refuser tout mismatch
 * (réattribution d'email / compte Google compromis) et auditer la liaison.
 */
class GoogleIdentityBindingTest extends TestCase
{
    use RefreshTenantDatabase;

    private function mockGoogleUser(string $email, string $googleId): void
    {
        $abstractUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn($email);
        $abstractUser->shouldReceive('getName')->andReturn('Google User');
        $abstractUser->shouldReceive('getId')->andReturn($googleId);
        $abstractUser->shouldReceive('offsetGet')->with('given_name')->andReturn('Google');
        $abstractUser->shouldReceive('offsetGet')->with('family_name')->andReturn('User');
        $abstractUser->shouldReceive('getRaw')->andReturn(['email' => $email, 'email_verified' => true]);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider = \Mockery::mock('Laravel\Socialite\Two\GoogleProvider'));
        $provider->shouldReceive('stateless')->andReturn($provider);
        $provider->shouldReceive('user')->andReturn($abstractUser);
    }

    private function employee(string $email, ?string $googleId = null): Employee
    {
        $employee = Employee::forceCreate([
            'first_name' => 'Existing',
            'last_name' => 'User',
            'email' => $email,
            'password_hash' => Hash::make('secret1234'),
            'google_id' => $googleId,
        ]);
        $employee->company_id = null;
        $employee->role = 'ordinary';
        $employee->status = 'active';
        $employee->save();

        return $employee;
    }

    public function test_first_google_login_binds_google_id(): void
    {
        $employee = $this->employee('existing@example.com');
        $this->assertNull($employee->google_id);

        $this->mockGoogleUser('existing@example.com', 'google-sub-111');

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertOk();
        $this->assertSame('google-sub-111', $employee->fresh()->google_id);
    }

    public function test_google_login_with_matching_google_id_succeeds(): void
    {
        $this->employee('existing@example.com', 'google-sub-111');

        $this->mockGoogleUser('existing@example.com', 'google-sub-111');

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertOk();
        $this->assertSame('google-sub-111', Employee::query()->where('email', 'existing@example.com')->value('google_id'));
    }

    public function test_google_login_with_mismatched_google_id_is_rejected(): void
    {
        // Réattribution d'email Google Workspace : le nouveau titulaire a un
        // sub différent → refus, même avec le bon email.
        $this->employee('existing@example.com', 'google-sub-111');

        $this->mockGoogleUser('existing@example.com', 'google-sub-ATTACKER');

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertStatus(401)
            ->assertJsonPath('error', 'GOOGLE_IDENTITY_MISMATCH');
        // L'identité liée n'est pas écrasée par l'attaquant.
        $this->assertSame('google-sub-111', Employee::query()->where('email', 'existing@example.com')->value('google_id'));
    }
}
