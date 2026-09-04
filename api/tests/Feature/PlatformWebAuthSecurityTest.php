<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Infrastructure\Services\SuperAdminService;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * audit(securite) #6530 — login web super-admin (surface /platform/login) :
 * challenge TOTP quand le secret existe + contrôle du statut, parité avec
 * l'API jumelle (Core/Auth/.../PlatformAuthController).
 */
class PlatformWebAuthSecurityTest extends TestCase
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

    /** @return array{_token: string} */
    private function csrf(): array
    {
        $this->get('/platform/login');
        $token = session()->token();

        return ['_token' => $token];
    }

    private function totpCode(string $secret): string
    {
        $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $normalized = strtoupper(rtrim($secret, '='));
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
            $decoded .= chr((int) bindec($chunk));
        }

        $time = intdiv(time(), 30);
        $binary = pack('N*', 0).pack('N', $time);
        $hash = hash_hmac('sha1', $binary, $decoded, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $unpacked = unpack('N', substr($hash, $offset, 4));

        return str_pad(
            (string) ((($unpacked !== false ? $unpacked[1] : 0) & 0x7FFFFFFF) % 1000000),
            6,
            '0',
            STR_PAD_LEFT
        );
    }

    public function test_web_login_with_2fa_enabled_requires_code(): void
    {
        $this->superAdmin->forceFill(['two_fa_secret' => (new SuperAdminService)->generateSecret()])->save();

        $response = $this->from('/platform/login')
            ->withSession($this->csrf())
            ->post('/platform/login', [
                '_token' => session()->token(),
                'email' => 'admin@leopardo.test',
                'password' => 'password123',
            ]);

        $response->assertSessionHasErrors('two_fa_code');
        $this->assertGuest('super_admin_web');
    }

    public function test_web_login_rejects_invalid_2fa_code(): void
    {
        $this->superAdmin->forceFill(['two_fa_secret' => (new SuperAdminService)->generateSecret()])->save();

        $response = $this->from('/platform/login')
            ->withSession($this->csrf())
            ->post('/platform/login', [
                '_token' => session()->token(),
                'email' => 'admin@leopardo.test',
                'password' => 'password123',
                'two_fa_code' => '123456',
            ]);

        $response->assertSessionHasErrors('two_fa_code');
        $this->assertGuest('super_admin_web');
    }

    public function test_web_login_with_valid_2fa_code_succeeds(): void
    {
        $secret = (new SuperAdminService)->generateSecret();
        $this->superAdmin->forceFill(['two_fa_secret' => $secret])->save();

        $response = $this->from('/platform/login')
            ->withSession($this->csrf())
            ->post('/platform/login', [
                '_token' => session()->token(),
                'email' => 'admin@leopardo.test',
                'password' => 'password123',
                'two_fa_code' => $this->totpCode($secret),
            ]);

        $response->assertRedirect(route('platform.companies.index'));
        $this->assertAuthenticated('super_admin_web');
    }

    public function test_web_login_refuses_deactivated_super_admin(): void
    {
        $this->superAdmin->forceFill(['status' => 'deactivated'])->save();

        $response = $this->from('/platform/login')
            ->withSession($this->csrf())
            ->post('/platform/login', [
                '_token' => session()->token(),
                'email' => 'admin@leopardo.test',
                'password' => 'password123',
            ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('super_admin_web');
    }

    public function test_web_login_without_2fa_still_works(): void
    {
        $response = $this->from('/platform/login')
            ->withSession($this->csrf())
            ->post('/platform/login', [
                '_token' => session()->token(),
                'email' => 'admin@leopardo.test',
                'password' => 'password123',
            ]);

        $response->assertRedirect(route('platform.companies.index'));
        $this->assertAuthenticated('super_admin_web');
    }
}
