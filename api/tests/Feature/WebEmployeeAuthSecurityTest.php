<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\TotpService;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * audit(securite) #6541 — login web employé (session) : challenge TOTP quand
 * le compte est enrôlé + verrouillage après tentatives échouées (parité
 * AuthService #2973/#5436).
 */
class WebEmployeeAuthSecurityTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $this->employee = new Employee(['email' => 'manager@company.test']);
        $this->employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $this->employee->forceFill([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /** @return array{_token: string} */
    private function csrf(): array
    {
        $this->get('/login');
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
            (string) (($unpacked !== false ? $unpacked[1] : 0) & 0x7FFFFFFF) % 1000000,
            6,
            '0',
            STR_PAD_LEFT
        );
    }

    public function test_web_login_requires_2fa_code_when_enabled(): void
    {
        $secret = (new TotpService)->generateSecret();
        $this->employee->forceFill([
            'two_fa_secret' => $secret,
            'two_fa_enabled_at' => now(),
        ])->save();

        $response = $this->from('/login')
            ->withSession($this->csrf())
            ->post('/login', [
                '_token' => session()->token(),
                'email' => 'manager@company.test',
                'password' => 'password123',
            ]);

        $response->assertSessionHasErrors('two_fa_code');
        $this->assertGuest('web');
    }

    public function test_web_login_rejects_invalid_2fa_code(): void
    {
        $secret = (new TotpService)->generateSecret();
        $this->employee->forceFill([
            'two_fa_secret' => $secret,
            'two_fa_enabled_at' => now(),
        ])->save();

        $response = $this->from('/login')
            ->withSession($this->csrf())
            ->post('/login', [
                '_token' => session()->token(),
                'email' => 'manager@company.test',
                'password' => 'password123',
                'two_fa_code' => '123456',
            ]);

        $response->assertSessionHasErrors('two_fa_code');
        $this->assertGuest('web');
    }

    public function test_web_login_with_valid_2fa_code_succeeds(): void
    {
        $secret = (new TotpService)->generateSecret();
        $this->employee->forceFill([
            'two_fa_secret' => $secret,
            'two_fa_enabled_at' => now(),
        ])->save();

        $response = $this->from('/login')
            ->withSession($this->csrf())
            ->post('/login', [
                '_token' => session()->token(),
                'email' => 'manager@company.test',
                'password' => 'password123',
                'two_fa_code' => $this->totpCode($secret),
            ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated('web');
    }

    public function test_web_login_locks_account_after_five_failures(): void
    {
        $token = $this->csrf()['_token'];

        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')
                ->withSession(['_token' => $token])
                ->post('/login', [
                    '_token' => $token,
                    'email' => 'manager@company.test',
                    'password' => 'wrong-password',
                ])->assertSessionHasErrors('email');
        }

        $fresh = $this->employee->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(5, (int) $fresh->failed_login_attempts);
        $this->assertNotNull($fresh->locked_until);
        $this->assertTrue(Carbon::parse($fresh->locked_until)->isFuture());
    }

    public function test_web_login_refuses_valid_credentials_while_locked(): void
    {
        $this->employee->forceFill([
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ])->save();

        $response = $this->from('/login')
            ->withSession($this->csrf())
            ->post('/login', [
                '_token' => session()->token(),
                'email' => 'manager@company.test',
                'password' => 'password123',
            ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_web_login_resets_failed_attempts_on_success(): void
    {
        $this->employee->forceFill([
            'failed_login_attempts' => 3,
            'locked_until' => null,
        ])->save();

        $this->from('/login')
            ->withSession($this->csrf())
            ->post('/login', [
                '_token' => session()->token(),
                'email' => 'manager@company.test',
                'password' => 'password123',
            ])->assertRedirect('/dashboard');

        $fresh = $this->employee->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(0, (int) $fresh->failed_login_attempts);
        $this->assertNull($fresh->locked_until);
    }
}
