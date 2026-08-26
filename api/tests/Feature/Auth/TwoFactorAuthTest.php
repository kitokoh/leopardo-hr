<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\TotpService;
use App\Core\Auth\Infrastructure\Services\TwoFactorAuthService;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanySetting;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5436 — 2FA/TOTP des comptes entreprise.
 *
 * Parcours : enrôlement (secret + QR) → confirmation (1er code) → login
 * avec challenge → verify (code TOTP ou code de récupération) → token ;
 * politique tenant `mfa_required_roles` ; désactivation ; remember device.
 */
class TwoFactorAuthTest extends TestCase
{
    use RefreshTenantDatabase;

    private function seedAccount(string $email, string $password = 'password123', string $role = 'employee', ?string $managerRole = null): array
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => $email,
            'password_hash' => Hash::make($password),
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        DB::table('public.user_lookups')->insertOrIgnore([
            'email' => $email,
            'company_id' => $company->id,
            'schema_name' => 'shared_tenants',
            'employee_id' => $employee->id,
            'role' => $role,
        ]);

        return [$company, $employee];
    }

    private function login(string $email, string $password = 'password123')
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * Code TOTP RFC 6238 (même algorithme que le fallback de TotpService —
     * google2fa n'est pas une dépendance du projet).
     */
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
            $decoded .= chr(bindec($chunk));
        }

        $counter = intdiv(time(), 30);
        $hash = hash_hmac('sha1', pack('N2', 0, $counter), $decoded, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function test_login_without_2fa_returns_token_directly(): void
    {
        $this->seedAccount('plain@example.com');

        $this->login('plain@example.com')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'email'], 'token']);
    }

    public function test_enroll_confirm_then_login_challenge_and_verify(): void
    {
        [$company, $employee] = $this->seedAccount('2fa@example.com');

        // Enrôlement (compte authentifié).
        $enroll = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/auth/2fa/enroll');
        $enroll->assertStatus(201)->assertJsonStructure(['data' => ['secret', 'qr_url']]);
        $secret = (string) $enroll->json('data.secret');

        // Code invalide → 422 TWO_FACTOR_INVALID, toujours pas activé.
        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/auth/2fa/confirm', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'TWO_FACTOR_INVALID');

        // Code valide → 201 + codes de récupération.
        $confirm = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/auth/2fa/confirm', ['code' => $this->totpCode($secret)]);
        $confirm->assertStatus(201)->assertJsonStructure(['data' => ['recovery_codes']]);
        $recoveryCode = (string) $confirm->json('data.recovery_codes.0');

        $employee->refresh();
        $this->assertNotNull($employee->two_fa_enabled_at);
        $this->assertNotSame($recoveryCode, $employee->two_fa_recovery_codes[0] ?? null, 'codes stockés hachés');

        // Login suivant → challenge (pas de token).
        $challenge = $this->login('2fa@example.com');
        $challenge->assertStatus(200)
            ->assertJsonPath('mfa_challenge', true)
            ->assertJsonStructure(['mfa_challenge_token', 'mfa_challenge_expires_in'])
            ->assertJsonMissingPath('token');
        $challengeToken = (string) $challenge->json('mfa_challenge_token');

        // Verify code invalide → 422.
        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $challengeToken,
            'code' => '000000',
        ])->assertStatus(422)->assertJsonPath('error', 'TWO_FACTOR_INVALID');

        // Verify code valide → token Sanctum.
        $verify = $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $challengeToken,
            'code' => $this->totpCode($secret),
            'device_name' => 'test-device',
        ]);
        $verify->assertStatus(200)->assertJsonStructure(['token', 'token_type']);
        $token = (string) $verify->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_challenge_token_is_single_use(): void
    {
        $this->seedAccount('2fa-single@example.com');
        $employee = Employee::where('email', '2fa-single@example.com')->firstOrFail();
        $secret = (new TotpService)->generateSecret();
        $employee->forceFill([
            'two_fa_secret' => $secret,
            'two_fa_enabled_at' => now(),
            'two_fa_recovery_codes' => [],
        ])->save();

        $challengeToken = (string) $this->login('2fa-single@example.com')->json('mfa_challenge_token');

        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $challengeToken,
            'code' => $this->totpCode($secret),
        ])->assertStatus(200);

        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $challengeToken,
            'code' => $this->totpCode($secret),
        ])->assertStatus(401)->assertJsonPath('error', 'TWO_FACTOR_CHALLENGE_EXPIRED');
    }

    public function test_recovery_code_is_single_use(): void
    {
        [$company, $employee] = $this->seedAccount('2fa-recovery@example.com');
        $secret = (new TotpService)->generateSecret();
        $recoveryPlain = ['ABC123DEF456', 'GHI789JKL012'];
        $employee->forceFill([
            'two_fa_secret' => $secret,
            'two_fa_enabled_at' => now(),
            'two_fa_recovery_codes' => array_map(static fn (string $c): string => hash('sha256', $c), $recoveryPlain),
        ])->save();

        $challengeToken = (string) $this->login('2fa-recovery@example.com')->json('mfa_challenge_token');

        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $challengeToken,
            'recovery_code' => $recoveryPlain[0],
        ])->assertStatus(200);

        // Même code de récupération → consommé → invalide.
        $challenge2 = (string) $this->login('2fa-recovery@example.com')->json('mfa_challenge_token');
        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $challenge2,
            'recovery_code' => $recoveryPlain[0],
        ])->assertStatus(422)->assertJsonPath('error', 'TWO_FACTOR_INVALID');
    }

    public function test_policy_requires_mfa_blocks_login(): void
    {
        [$company, $employee] = $this->seedAccount('rh@example.com', role: 'manager', managerRole: 'rh');

        CompanySetting::query()->create([
            'key' => 'mfa_required_roles',
            'value' => 'rh,principal',
            'value_type' => 'string',
        ]);

        // RH sans 2FA → login bloqué (403 TWO_FACTOR_REQUIRED).
        $this->login('rh@example.com')
            ->assertStatus(403)
            ->assertJsonPath('error', 'TWO_FACTOR_REQUIRED');
    }

    public function test_disable_requires_code(): void
    {
        [$company, $employee] = $this->seedAccount('2fa-disable@example.com');
        $secret = (new TotpService)->generateSecret();
        $employee->forceFill([
            'two_fa_secret' => $secret,
            'two_fa_enabled_at' => now(),
            'two_fa_recovery_codes' => [],
        ])->save();

        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/auth/2fa/disable', ['code' => '000000'])
            ->assertStatus(422);

        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/auth/2fa/disable', ['code' => $this->totpCode($secret)])
            ->assertStatus(200)
            ->assertJsonPath('data.enabled', false);

        $employee->refresh();
        $this->assertNull($employee->two_fa_enabled_at);
    }

    public function test_remember_device_skips_challenge_on_next_login(): void
    {
        [$company, $employee] = $this->seedAccount('2fa-remember@example.com');
        $secret = (new TotpService)->generateSecret();
        $employee->forceFill([
            'two_fa_secret' => $secret,
            'two_fa_enabled_at' => now(),
            'two_fa_recovery_codes' => [],
        ])->save();

        $challengeToken = (string) $this->login('2fa-remember@example.com')->json('mfa_challenge_token');

        $verify = $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $challengeToken,
            'code' => $this->totpCode($secret),
            'remember_device' => true,
        ]);
        $verify->assertStatus(200);

        // En test, le middleware EncryptCookies est désactivé pour ce cookie :
        // on transmet la valeur HMAC attendue (le cookie réel est chiffré).
        // NB #5579 : en Laravel 12 le pipeline résout son PROPRE instance de
        // middleware — un `make()->disableFor()` jetable n'affecte pas la
        // requête. On pré-lie l'instance configurée dans le conteneur.
        $cookieMiddleware = $this->app->make(EncryptCookies::class);
        $cookieMiddleware->disableFor('mfa_remember_'.$employee->id);
        $this->app->instance(EncryptCookies::class, $cookieMiddleware);
        $expected = app(TwoFactorAuthService::class)
            ->rememberCookieValue($employee);

        // Login suivant AVEC le cookie → token direct (pas de challenge).
        // NB #5579 : avecCredentials() est OBLIGATOIRE — sans lui, les
        // requêtes JSON (postJson) n'emportent AUCUN cookie
        // (prepareCookiesForJsonRequest → []).
        $this->withCredentials()
            ->withUnencryptedCookie('mfa_remember_'.$employee->id, $expected)
            ->postJson('/api/v1/auth/login', [
                'email' => '2fa-remember@example.com',
                'password' => 'password123',
            ])
            ->assertStatus(200)
            ->assertJsonStructure(['token'])
            ->assertJsonMissingPath('mfa_challenge');
    }
}
