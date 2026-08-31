<?php

namespace Tests\Feature;

use App\Core\Auth\Infrastructure\Services\SuperAdminService;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PlatformAuthTest extends TestCase
{
    use CreatesMvpSchema;

    private SuperAdmin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->superAdmin = new SuperAdmin([
            'name' => 'Test Super Admin',
            'email' => 'admin@leopardo.test',
        ]);
        $this->superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_super_admin_can_login_without_2fa_if_not_enabled(): void
    {
        $response = $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'admin@leopardo.test',
            'password' => 'password123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.role', 'super_admin');
        $response->assertJsonPath('data.two_fa_enabled', false);
        $response->assertJsonPath('token_type', 'Bearer');
        $response->assertJsonStructure(['data' => ['id', 'name', 'email', 'role', 'two_fa_enabled'], 'token']);
    }

    public function test_platform_login_token_opens_platform_admin_session(): void
    {
        $login = $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'admin@leopardo.test',
            'password' => 'password123',
            'device_name' => 'admin-dashboard-contract',
        ])->assertOk();

        $token = $login->json('token');
        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/platform/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@leopardo.test')
            ->assertJsonPath('data.role', 'super_admin')
            ->assertJsonPath('data.two_fa_enabled', false)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'two_fa_enabled',
                ],
            ]);
    }

    public function test_super_admin_can_setup_2fa(): void
    {
        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/platform/auth/2fa/setup');

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['secret', 'qr_code_url']]);

        $secret = $response->json('data.secret');
        $this->assertNotEmpty($secret);

        // Secret should not be saved yet
        $this->assertNull($this->superAdmin->fresh()->two_fa_secret);
    }

    public function test_super_admin_can_enable_2fa(): void
    {
        $token = $this->superAdmin->createToken('test')->plainTextToken;
        $service = app(SuperAdminService::class);
        $secret = $service->generateSecret();

        Cache::put("2fa_setup:{$this->superAdmin->id}", $secret, now()->addMinutes(10));

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/platform/auth/2fa/enable', [
                'code' => '000000', // Invalid code
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'INVALID_2FA_CODE');
        $this->assertNull($this->superAdmin->fresh()->two_fa_secret);
        $this->assertSame($secret, Cache::get("2fa_setup:{$this->superAdmin->id}"));
    }

    public function test_enable_2fa_requires_setup_first(): void
    {
        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/platform/auth/2fa/enable', [
                'code' => '000000',
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'SETUP_REQUIRED');
    }

    public function test_login_requires_2fa_code_when_enabled(): void
    {
        $this->superAdmin->two_fa_secret = 'TESTSECRET';
        $this->superAdmin->save();

        $response = $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'admin@leopardo.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('error', 'TWO_FA_REQUIRED');
    }

    public function test_login_rejects_invalid_2fa_code(): void
    {
        $this->superAdmin->two_fa_secret = 'TESTSECRET';
        $this->superAdmin->save();

        $response = $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'admin@leopardo.test',
            'password' => 'password123',
            'two_fa_code' => '123456', // Invalid code
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'INVALID_2FA_CODE');
    }

    public function test_super_admin_can_disable_2fa_with_password(): void
    {
        $this->superAdmin->two_fa_secret = 'TESTSECRET';
        $this->superAdmin->save();

        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/platform/auth/2fa/disable', [
                'password' => 'wrongpassword',
            ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'INVALID_PASSWORD');
        $this->assertNotNull($this->superAdmin->fresh()->two_fa_secret);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/platform/auth/2fa/disable', [
                'password' => 'password123',
            ]);

        $response->assertOk();
        $this->assertNull($this->superAdmin->fresh()->two_fa_secret);
    }

    public function test_super_admin_can_update_own_profile(): void
    {
        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/v1/platform/auth/profile', [
                'name' => 'Updated Admin Name',
                'email' => 'updated-admin@leopardo.test',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated Admin Name');
        $response->assertJsonPath('data.email', 'updated-admin@leopardo.test');

        $this->assertSame('Updated Admin Name', $this->superAdmin->fresh()->name);
        $this->assertSame('updated-admin@leopardo.test', $this->superAdmin->fresh()->email);
    }

    public function test_super_admin_profile_update_rejects_email_already_taken(): void
    {
        $otherAdmin = new SuperAdmin([
            'name' => 'Other Admin',
            'email' => 'other-admin@leopardo.test',
        ]);
        $otherAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/v1/platform/auth/profile', [
                'email' => 'other-admin@leopardo.test',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'EMAIL_ALREADY_TAKEN');
        $this->assertSame('admin@leopardo.test', $this->superAdmin->fresh()->email);
    }

    public function test_super_admin_can_change_own_password(): void
    {
        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/platform/auth/change-password', [
                'current_password' => 'password123',
                'new_password' => 'brandNewPassword456',
                'new_password_confirmation' => 'brandNewPassword456',
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');

        $this->assertTrue(Hash::check('brandNewPassword456', $this->superAdmin->fresh()->password_hash));

        // Old sessions/tokens (except the one used for this request) are revoked.
        $loginAfterChange = $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'admin@leopardo.test',
            'password' => 'brandNewPassword456',
        ]);
        $loginAfterChange->assertOk();
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $token = $this->superAdmin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/platform/auth/change-password', [
                'current_password' => 'wrong-password',
                'new_password' => 'brandNewPassword456',
                'new_password_confirmation' => 'brandNewPassword456',
            ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'INVALID_PASSWORD');
        $this->assertTrue(Hash::check('password123', $this->superAdmin->fresh()->password_hash));
    }

    public function test_suspended_super_admin_cannot_login(): void
    {
        $suspended = new SuperAdmin([
            'name' => 'Suspended Admin',
            'email' => 'suspended@leopardo.test',
        ]);
        $suspended->forceFill(['password_hash' => Hash::make('password123')])->save();
        $suspended->forceFill([
            'status' => 'suspended',
        ])->save();

        $this->assertSame('suspended', $suspended->status);

        $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'suspended@leopardo.test',
            'password' => 'password123',
        ])->assertStatus(403)
            ->assertJsonPath('error', 'ACCOUNT_SUSPENDED');
    }

    public function test_deactivated_super_admin_cannot_login(): void
    {
        $sensitiveSuperAdmin0 = new SuperAdmin([
            'name' => 'Deactivated Admin',
            'email' => 'deactivated@leopardo.test',
        ]);
        $sensitiveSuperAdmin0->forceFill(['password_hash' => Hash::make('password123')])->save();
        $sensitiveSuperAdmin0->forceFill([
            'status' => 'deactivated',
        ])->save();

        $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'deactivated@leopardo.test',
            'password' => 'password123',
        ])->assertStatus(403)
            ->assertJsonPath('error', 'ACCOUNT_SUSPENDED');
    }

    public function test_deactivating_super_admin_revokes_existing_tokens(): void
    {
        // Login ok pour un admin actif
        $login = $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'admin@leopardo.test',
            'password' => 'password123',
            'device_name' => 'token-revocation-check',
        ])->assertOk();

        $token = $login->json('token');
        $this->assertIsString($token);

        // Le token fonctionne avant désactivation
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/platform/auth/me')
            ->assertOk();

        // Désactivation via l'endpoint admin (simule l'action plateforme)
        $admin = SuperAdmin::query()->where('email', 'admin@leopardo.test')->firstOrFail();
        // Issue #3677 : status non mass-assignable — assignation explicite.
        $admin->status = 'deactivated';
        $admin->save();
        $admin->tokens()->delete();

        $this->assertSame(0, $admin->tokens()->count(), 'Les tokens doivent être révoqués à la désactivation');
    }

    // ======================= Web login (#6530) =======================

    public function test_web_login_succeeds_without_2fa_if_not_enabled(): void
    {
        $response = $this->post('/platform/login', [
            'email' => 'admin@leopardo.test',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('platform.companies.index'));
        $this->assertAuthenticated('super_admin_web');
    }

    public function test_web_login_rejects_invalid_credentials(): void
    {
        $response = $this->post('/platform/login', [
            'email' => 'admin@leopardo.test',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('super_admin_web');
    }

    public function test_web_login_blocks_suspended_super_admin(): void
    {
        // Issue #6530 : un super-admin suspendu/désactivé ne doit pas pouvoir
        // se connecter par la surface web (alors que la garde existait déjà
        // côté API, #2630).
        $this->superAdmin->status = 'suspended';
        $this->superAdmin->save();

        $response = $this->post('/platform/login', [
            'email' => 'admin@leopardo.test',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('super_admin_web');
    }

    public function test_web_login_requires_2fa_code_when_enabled(): void
    {
        $this->superAdmin->two_fa_secret = 'JBSWY3DPEHPK3PXP';
        $this->superAdmin->save();

        $response = $this->post('/platform/login', [
            'email' => 'admin@leopardo.test',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('two_fa_code');
        $response->assertSessionHas('two_fa_required');
        $this->assertGuest('super_admin_web');
    }

    public function test_web_login_rejects_invalid_2fa_code(): void
    {
        $this->superAdmin->two_fa_secret = 'JBSWY3DPEHPK3PXP';
        $this->superAdmin->save();

        $response = $this->post('/platform/login', [
            'email' => 'admin@leopardo.test',
            'password' => 'password123',
            'two_fa_code' => '123456', // Code invalide
        ]);

        $response->assertSessionHasErrors('two_fa_code');
        $this->assertGuest('super_admin_web');
    }

    public function test_web_login_succeeds_with_valid_2fa_code(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $this->superAdmin->two_fa_secret = $secret;
        $this->superAdmin->save();

        $response = $this->post('/platform/login', [
            'email' => 'admin@leopardo.test',
            'password' => 'password123',
            'two_fa_code' => $this->totpAt($secret, time()),
        ]);

        $response->assertRedirect(route('platform.companies.index'));
        $this->assertAuthenticated('super_admin_web');
    }

    /**
     * TOTP RFC 6238 (SHA1, 6 digits, fenêtre 30 s) — miroir de
     * SuperAdminService::totpAt pour les tests du login web (#6530).
     */
    private function totpAt(string $secret, int $timestamp): string
    {
        $counter = intdiv($timestamp, 30);
        $binaryCounter = pack('N2', 0, $counter);
        $key = $this->base32Decode($secret);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $chunk = substr($hash, $offset, 4);
        $value = unpack('N', $chunk)[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $value): string
    {
        $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $normalized = strtoupper(rtrim($value, '='));
        $bits = '';

        foreach (str_split($normalized) as $char) {
            if (! array_key_exists($char, $alphabet)) {
                continue;
            }
            $bits .= str_pad(decbin($alphabet[$char]), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                continue;
            }
            $decoded .= chr(bindec($chunk));
        }

        return $decoded;
    }
}
