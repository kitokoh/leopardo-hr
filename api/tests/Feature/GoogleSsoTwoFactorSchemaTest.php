<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * audit(securite) #6540 — la 2FA des flux SSO/Google doit être émise avec le
 * tenant_schema résolu (mode schéma-par-tenant) : un challenge avec null rend
 * la vérification impossible (search_path introuvable au verifyChallenge).
 */
class GoogleSsoTwoFactorSchemaTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

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

    private function seedTenantEmployeeWith2fa(string $email, string $googleId): void
    {
        $company = Company::query()->create([
            'name' => 'Company Schema',
            'slug' => 'company-schema',
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'schema@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $employee = new Employee(['email' => $email, 'google_id' => $googleId]);
        $employee->forceFill(['password_hash' => Hash::make('secret1234')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
            'two_fa_secret' => 'TESTSECRET',
            'two_fa_enabled_at' => now(),
        ])->save();
    }

    public function test_google_callback_2fa_challenge_carries_resolved_tenant_schema(): void
    {
        $this->seedTenantEmployeeWith2fa('schema@example.com', 'google-sub-schema');
        $this->mockGoogleUser('schema@example.com', 'google-sub-schema');

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertOk()->assertJsonPath('mfa_challenge', true);

        $challengeToken = $response->json('mfa_challenge_token');
        $this->assertIsString($challengeToken);

        $context = Cache::get('mfa:challenge:'.$challengeToken);
        $this->assertIsArray($context);
        // #6540 : le schéma tenant résolu est transporté dans le challenge.
        $this->assertSame('shared_tenants', $context['tenant_schema'] ?? null);
    }
}
