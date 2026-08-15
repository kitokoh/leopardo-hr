<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Auth\Infrastructure\Services\UserAuthService;
use App\Exceptions\AccountSuspendedException;
use App\Exceptions\InvalidCredentialsException;
use App\Core\Auth\Domain\Models\User;
use Tests\RefreshTenantDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Issue #2618 — login email + Google refusés pour un compte suspendu
 * (status !== 'active') : aucun token émis (fail-closed).
 */
class SuspendedLoginTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_email_login_rejected_when_account_suspended(): void
    {
        User::factory()->create([
            'email' => 'suspended@example.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'suspended',
        ]);

        $this->expectException(AccountSuspendedException::class);

        (new UserAuthService)->login('suspended@example.com', 'password123');
    }

    public function test_email_login_ok_when_account_active(): void
    {
        User::factory()->create([
            'email' => 'active@example.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $result = (new UserAuthService)->login('active@example.com', 'password123');
        $this->assertArrayHasKey('token', $result);
        $this->assertSame('active@example.com', $result['user']->email);
    }

    public function test_email_login_still_rejects_bad_password_for_suspended(): void
    {
        User::factory()->create([
            'email' => 'suspended2@example.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'suspended',
        ]);

        // La vérification de suspension prime sur le mot de passe (aucune
        // information de plus donnée sur le compte).
        $this->expectException(AccountSuspendedException::class);

        (new UserAuthService)->login('suspended2@example.com', 'wrong-password');
    }

    public function test_google_sign_in_rejected_when_account_suspended(): void
    {
        User::factory()->create([
            'email' => 'suspended-google@example.com',
            'google_id' => 'google-123',
            'status' => 'disabled',
        ]);

        $this->expectException(AccountSuspendedException::class);

        (new UserAuthService)->googleSignIn('google-123', 'suspended-google@example.com', 'Jane', 'Doe');
    }

    public function test_google_sign_in_ok_when_account_active(): void
    {
        User::factory()->create([
            'email' => 'active-google@example.com',
            'google_id' => 'google-456',
            'status' => 'active',
        ]);

        $result = (new UserAuthService)->googleSignIn('google-456', 'active-google@example.com', 'Jane', 'Doe');
        $this->assertArrayHasKey('token', $result);
        $this->assertFalse($result['is_new']);
    }
}
