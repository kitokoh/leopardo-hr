<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Core\Auth\Domain\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CreatesMvpSchema;
use Tests\Support\SignsGoogleIdTokens;
use Tests\TestCase;

/**
 * Issue #3941 — /api/v1/user/google-signin ne doit JAMAIS émettre de token
 * sans vérification serveur de l'identité Google (ID token signé, iss, aud,
 * exp, email_verified). L'identité du compte provient exclusivement des
 * claims vérifiés — les champs fournis par le client sont ignorés.
 */
class UserAuthGoogleSignInSecurityTest extends TestCase
{
    use CreatesMvpSchema;
    use SignsGoogleIdTokens;

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

    public function test_missing_id_token_returns_422(): void
    {
        $this->postJson('/api/v1/user/google-signin', [
            'google_id' => 'forged',
            'email' => 'victim@example.com',
        ])->assertStatus(422)->assertJsonValidationErrors(['id_token']);
    }

    public function test_forged_token_returns_401_no_token_issued(): void
    {
        [$privateKey, $jwks] = $this->googleKeyPair();
        [$otherKey, $otherJwks] = $this->googleKeyPair();
        $this->fakeGoogleJwks([$otherJwks]);

        // Token signé par une AUTRE clé que le JWKS servi → signature invalide.
        $forged = $this->googleIdToken($privateKey);

        $this->postJson('/api/v1/user/google-signin', ['id_token' => $forged])
            ->assertStatus(401)
            ->assertJsonPath('error', 'GOOGLE_TOKEN_INVALID');

        $this->assertDatabaseMissing('users', ['email' => 'verified.user@example.com']);
    }

    public function test_valid_token_creates_account_from_verified_claims(): void
    {
        [$privateKey, $jwks] = $this->googleKeyPair();
        $this->fakeGoogleJwks([$jwks]);

        $idToken = $this->googleIdToken($privateKey, [
            'email' => 'real.user@example.com',
            'name' => 'Jane Doe',
            'sub' => 'google-sub-456',
        ]);

        $response = $this->postJson('/api/v1/user/google-signin', [
            'id_token' => $idToken,
            // Champs hérités envoyés par un client malveillant : DOIVENT être ignorés.
            'google_id' => 'attacker-controlled',
            'email' => 'attacker@example.com',
            'first_name' => 'Attacker',
            'last_name' => 'Name',
        ])->assertOk();

        $response->assertJsonPath('is_new', true);
        $response->assertJsonPath('data.email', 'real.user@example.com');
        $this->assertNotEmpty($response->json('token'));

        $user = User::query()->where('email', 'real.user@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-sub-456', $user->google_id);
        $this->assertSame('Jane', $user->first_name);
        $this->assertSame('Doe', $user->last_name);

        // Aucun compte créé avec l'identité fournie par le client.
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);
    }

    public function test_valid_token_links_existing_account_by_verified_email(): void
    {
        [$privateKey, $jwks] = $this->googleKeyPair();
        $this->fakeGoogleJwks([$jwks]);

        User::query()->create([
            'first_name' => 'Existing',
            'last_name' => 'User',
            'email' => 'real.user@example.com',
            'provider' => 'email',
        ]);

        $idToken = $this->googleIdToken($privateKey, [
            'email' => 'real.user@example.com',
            'sub' => 'google-sub-789',
        ]);

        $this->postJson('/api/v1/user/google-signin', ['id_token' => $idToken])
            ->assertOk()
            ->assertJsonPath('is_new', false);

        $user = User::query()->where('email', 'real.user@example.com')->first();
        $this->assertSame('google-sub-789', $user->google_id, 'Lien Google attaché au compte existant.');
    }

    public function test_expired_token_returns_401(): void
    {
        [$privateKey, $jwks] = $this->googleKeyPair();
        $this->fakeGoogleJwks([$jwks]);

        $expired = $this->googleIdToken($privateKey, ['exp' => time() - 3600]);

        $this->postJson('/api/v1/user/google-signin', ['id_token' => $expired])
            ->assertStatus(401)
            ->assertJsonPath('error', 'GOOGLE_TOKEN_INVALID');
    }

    public function test_unverified_email_returns_401(): void
    {
        [$privateKey, $jwks] = $this->googleKeyPair();
        $this->fakeGoogleJwks([$jwks]);

        $unverified = $this->googleIdToken($privateKey, ['email_verified' => false]);

        $this->postJson('/api/v1/user/google-signin', ['id_token' => $unverified])
            ->assertStatus(401)
            ->assertJsonPath('error', 'GOOGLE_TOKEN_INVALID');
    }

    public function test_wrong_issuer_returns_401(): void
    {
        [$privateKey, $jwks] = $this->googleKeyPair();
        $this->fakeGoogleJwks([$jwks]);

        $wrongIssuer = $this->googleIdToken($privateKey, ['iss' => 'https://evil.example.com']);

        $this->postJson('/api/v1/user/google-signin', ['id_token' => $wrongIssuer])
            ->assertStatus(401)
            ->assertJsonPath('error', 'GOOGLE_TOKEN_INVALID');
    }

    public function test_audience_is_enforced_when_client_id_configured(): void
    {
        config()->set('services.google.client_id', 'leopardo-backend-client');
        config()->set('services.google.android_client_id', 'leopardo-mobile-client');

        [$privateKey, $jwks] = $this->googleKeyPair();
        $this->fakeGoogleJwks([$jwks]);

        // aud hors liste autorisée → refusé.
        $badAud = $this->googleIdToken($privateKey, ['aud' => 'another-app-client']);
        $this->postJson('/api/v1/user/google-signin', ['id_token' => $badAud])
            ->assertStatus(401)
            ->assertJsonPath('error', 'GOOGLE_TOKEN_INVALID');

        // aud dans la liste (client mobile) → accepté.
        $goodAud = $this->googleIdToken($privateKey, ['aud' => 'leopardo-mobile-client']);
        $this->postJson('/api/v1/user/google-signin', ['id_token' => $goodAud])
            ->assertOk();
    }

    public function test_suspended_existing_user_returns_403(): void
    {
        [$privateKey, $jwks] = $this->googleKeyPair();
        $this->fakeGoogleJwks([$jwks]);

        User::query()->create([
            'first_name' => 'Suspended',
            'last_name' => 'User',
            'email' => 'suspended.user@example.com',
            'google_id' => 'google-sub-susp',
            'provider' => 'google',
            'status' => 'suspended',
        ]);

        $idToken = $this->googleIdToken($privateKey, [
            'email' => 'suspended.user@example.com',
            'sub' => 'google-sub-susp',
        ]);

        $this->postJson('/api/v1/user/google-signin', ['id_token' => $idToken])
            ->assertStatus(403)
            ->assertJsonPath('error', 'ACCOUNT_SUSPENDED');
    }
}
