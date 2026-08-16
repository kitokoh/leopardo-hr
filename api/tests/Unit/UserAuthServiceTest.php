<?php

namespace Tests\Unit;

use App\Core\Auth\Domain\Models\User;
use App\Core\Auth\Infrastructure\Services\UserAuthService;
use App\Exceptions\AccountSuspendedException;
use App\Exceptions\GoogleTokenInvalidException;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\Support\SignsGoogleIdTokens;
use Tests\TestCase;

class UserAuthServiceTest extends TestCase
{
    use CreatesMvpSchema;
    use SignsGoogleIdTokens;

    private UserAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->service = new UserAuthService;
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_login_success_for_active_user(): void
    {
        User::query()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
            'password_hash' => Hash::make('secret123'),
            'provider' => 'email',
        ]);

        $result = $this->service->login('jean.dupont@example.com', 'secret123', 'test');

        $this->assertArrayHasKey('token', $result);
        $this->assertSame('Bearer', $result['token_type']);
    }

    public function test_suspended_user_cannot_login(): void
    {
        $createdUser = User::query()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
            'password_hash' => Hash::make('secret123'),
            'provider' => 'email',
        ]);
            $createdUser->status = 'suspended';
            $createdUser->save();


        $this->expectException(AccountSuspendedException::class);
        $this->service->login('jean.dupont@example.com', 'secret123', 'test');
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $createdUser = User::query()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
            'password_hash' => Hash::make('secret123'),
            'provider' => 'email',
        ]);
            $createdUser->status = 'deactivated';
            $createdUser->save();


        $this->expectException(AccountSuspendedException::class);
        $this->service->login('jean.dupont@example.com', 'secret123', 'test');
    }

    public function test_suspended_account_is_rejected_even_with_wrong_password(): void
    {
        // Fail-closed (#2618, main) : le statut est vérifié AVANT le mot de
        // passe — un compte suspendu ne révèle jamais la validité du mot de passe.
        $createdUser = User::query()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
            'password_hash' => Hash::make('secret123'),
            'provider' => 'email',
        ]);
            $createdUser->status = 'suspended';
            $createdUser->save();


        $this->expectException(AccountSuspendedException::class);
        $this->service->login('jean.dupont@example.com', 'wrong-password', 'test');
    }

    public function test_google_sign_in_rejects_suspended_existing_user(): void
    {
        [$privateKey, $jwks] = $this->googleKeyPair();
        $this->fakeGoogleJwks([$jwks]);

        $createdUser = User::query()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
            'google_id' => 'google-sub-123',
            'provider' => 'google',
        ]);
            $createdUser->status = 'suspended';
            $createdUser->save();


        $idToken = $this->googleIdToken($privateKey, ['email' => 'jean.dupont@example.com']);

        $this->expectException(AccountSuspendedException::class);
        $this->service->googleSignIn($idToken);
    }

    public function test_google_sign_in_rejects_forged_token(): void
    {
        [$privateKey, $jwks] = $this->googleKeyPair();
        // JWKS servi = AUTRE clé que celle qui signe le token (signature invalide).
        [$otherKey, $otherJwks] = $this->googleKeyPair();
        $this->fakeGoogleJwks([$otherJwks]);

        $idToken = $this->googleIdToken($privateKey);

        $this->expectException(GoogleTokenInvalidException::class);
        $this->service->googleSignIn($idToken);
    }

    public function test_google_sign_in_uses_verified_identity_only(): void
    {
        [$privateKey, $jwks] = $this->googleKeyPair();
        $this->fakeGoogleJwks([$jwks]);

        $idToken = $this->googleIdToken($privateKey, [
            'email' => 'verified.user@example.com',
            'name' => 'Jane Doe',
        ]);

        $result = $this->service->googleSignIn($idToken);

        $this->assertArrayHasKey('token', $result);
        $this->assertTrue($result['is_new']);
        $this->assertSame('verified.user@example.com', $result['user']->email);
        $this->assertSame('google-sub-123', $result['user']->google_id);
        $this->assertSame('Jane', $result['user']->first_name);
        $this->assertSame('Doe', $result['user']->last_name);
    }
}
