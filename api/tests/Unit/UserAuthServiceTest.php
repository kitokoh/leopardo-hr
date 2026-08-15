<?php

namespace Tests\Unit;

use App\Core\Auth\Domain\Models\User;
use App\Core\Auth\Infrastructure\Services\UserAuthService;
use App\Exceptions\AccountSuspendedException;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class UserAuthServiceTest extends TestCase
{
    use CreatesMvpSchema;

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
        User::query()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
            'password_hash' => Hash::make('secret123'),
            'provider' => 'email',
            'status' => 'suspended',
        ]);

        $this->expectException(AccountSuspendedException::class);
        $this->service->login('jean.dupont@example.com', 'secret123', 'test');
    }

    public function test_deactivated_user_cannot_login(): void
    {
        User::query()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
            'password_hash' => Hash::make('secret123'),
            'provider' => 'email',
            'status' => 'deactivated',
        ]);

        $this->expectException(AccountSuspendedException::class);
        $this->service->login('jean.dupont@example.com', 'secret123', 'test');
    }

    public function test_suspended_account_is_rejected_even_with_wrong_password(): void
    {
        // Fail-closed (#2618, main) : le statut est vérifié AVANT le mot de
        // passe — un compte suspendu ne révèle jamais la validité du mot de passe.
        User::query()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
            'password_hash' => Hash::make('secret123'),
            'provider' => 'email',
            'status' => 'suspended',
        ]);

        $this->expectException(AccountSuspendedException::class);
        $this->service->login('jean.dupont@example.com', 'wrong-password', 'test');
    }

    public function test_google_sign_in_rejects_suspended_existing_user(): void
    {
        User::query()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
            'google_id' => 'google-123',
            'provider' => 'google',
            'status' => 'suspended',
        ]);

        $this->expectException(AccountSuspendedException::class);
        $this->service->googleSignIn('google-123', 'jean.dupont@example.com', 'Jean', 'Dupont');
    }
}
