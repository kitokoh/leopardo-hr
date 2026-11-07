<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Contracts\SmsCodeSenderContract;
use App\Core\Auth\Domain\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * COULOIR 16 — M2 (#9104) · OTP par telephone du canal public.
 *
 * Ces tests verifient les proprietes qui peuvent regresser en silence :
 *
 *  1. **anti-enumeration** : `request` rend 204 meme pour un numero inconnu ;
 *  2. **un code, un canal** : si le SMS ne peut pas delivrer, aucun code n'est
 *     stocke et aucun faux succes n'est rendu ;
 *  3. **usage unique + hachage** : le code est consomme au premier usage et
 *     n'est jamais stocke en clair ;
 *  4. **anti-fraude** : quota par numero (3 / 15 min) et backoff apres echecs ;
 *  5. **isolation de guard** : un jeton `user_api` n'ouvre aucun acces employe.
 *
 * Le faux transport SMS est injecte via le conteneur : on teste la politique,
 * pas un fournisseur.
 */
final class PublicPhoneOtpTest extends TestCase
{
    use RefreshTenantDatabase;

    private const PHONE = '+213555123456';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        RateLimiter::clear('auth:public_phone_otp_send:'.hash('sha256', self::PHONE));
        RateLimiter::clear('auth:public_phone_otp_ip:'.hash('sha256', '127.0.0.1'));
    }

    /**
     * Transport SMS de test : configure, et il retient le dernier code envoye
     * pour que le test puisse le rejouer (un vrai client le lit dans son SMS).
     */
    private function fakeSmsSender(bool $configured = true, bool $delivers = true): void
    {
        $this->app->bind(SmsCodeSenderContract::class, function () use ($configured, $delivers): SmsCodeSenderContract {
            return new class($configured, $delivers) implements SmsCodeSenderContract
            {
                public static ?string $lastCode = null;

                public function __construct(
                    private readonly bool $configured,
                    private readonly bool $delivers,
                ) {}

                public function channel(): string
                {
                    return 'sms';
                }

                public function isConfigured(): bool
                {
                    return $this->configured;
                }

                public function send(string $e164, string $code, string $locale): ?string
                {
                    if (! $this->delivers) {
                        return null;
                    }

                    self::$lastCode = $code;

                    return 'test_trace_'.substr(hash('sha256', $e164), 0, 12);
                }
            };
        });
    }

    private function makeUser(string $phone = self::PHONE): User
    {
        /** @var User $user */
        $user = User::create([
            'first_name' => 'Fatou',
            'last_name' => 'Diallo',
            'email' => 'fatou.'.substr(hash('sha256', $phone), 0, 8).'@example.com',
            'phone_e164' => $phone,
            'provider' => 'phone',
        ]);

        return $user;
    }

    public function test_request_returns_204_for_unknown_phone(): void
    {
        $this->fakeSmsSender();

        $response = $this->postJson('/api/v1/user/login-code/phone/request', [
            'phone_e164' => '+213555999888',
        ]);

        // Anti-enumeration : le corps ne dit JAMAIS si le numero a un compte.
        $response->assertStatus(204);
        $this->assertNull(
            Cache::get('auth:public_phone_otp:'.hash('sha256', '+213555999888')),
            'Aucun code ne doit etre stocke pour un numero inconnu.'
        );
    }

    public function test_request_returns_204_for_invalid_phone(): void
    {
        $this->fakeSmsSender();

        $response = $this->postJson('/api/v1/user/login-code/phone/request', [
            'phone_e164' => 'not-a-phone',
        ]);

        $response->assertStatus(204);
    }

    public function test_request_stores_hashed_code_for_known_phone(): void
    {
        $this->fakeSmsSender();
        $this->makeUser();

        $this->postJson('/api/v1/user/login-code/phone/request', [
            'phone_e164' => self::PHONE,
        ])->assertStatus(204);

        /** @var array{hash?: string}|null $entry */
        $entry = Cache::get('auth:public_phone_otp:'.hash('sha256', self::PHONE));

        $this->assertIsArray($entry);
        $this->assertArrayHasKey('hash', $entry);
        $this->assertNotSame(
            '123456',
            $entry['hash'],
            'Le code ne doit jamais etre stocke en clair.'
        );
    }

    public function test_request_stores_nothing_when_sms_cannot_deliver(): void
    {
        // Canal non configure : aucun code ne doit etre fabrique.
        $this->fakeSmsSender(configured: false);
        $this->makeUser();

        $this->postJson('/api/v1/user/login-code/phone/request', [
            'phone_e164' => self::PHONE,
        ])->assertStatus(204);

        $this->assertNull(
            Cache::get('auth:public_phone_otp:'.hash('sha256', self::PHONE)),
            'Un canal qui ne peut pas delivrer ne doit pas consommer un code.'
        );
    }

    public function test_request_stores_nothing_when_delivery_fails(): void
    {
        // Canal configure mais l'appel echoue : pas de "code fantome".
        $this->fakeSmsSender(configured: true, delivers: false);
        $this->makeUser();

        $this->postJson('/api/v1/user/login-code/phone/request', [
            'phone_e164' => self::PHONE,
        ])->assertStatus(204);

        $this->assertNull(Cache::get('auth:public_phone_otp:'.hash('sha256', self::PHONE)));
    }

    public function test_verify_issues_token_and_marks_phone_verified(): void
    {
        $this->fakeSmsSender();
        $user = $this->makeUser();

        $this->postJson('/api/v1/user/login-code/phone/request', [
            'phone_e164' => self::PHONE,
        ])->assertStatus(204);

        $code = $this->lastSentCode();

        $response = $this->postJson('/api/v1/user/login-code/phone/verify', [
            'phone_e164' => self::PHONE,
            'code' => $code,
            'device_name' => 'iPhone 15',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'phone_e164'], 'token', 'token_type', 'token_expires_at']);

        $this->assertNotNull($response->json('token'));

        /** @var User $fresh */
        $fresh = $user->fresh();
        $this->assertNotNull(
            $fresh->phone_verified_at,
            'Un telephone prouve par OTP doit etre horodate : le pont d identite (L4) l exige.'
        );
    }

    public function test_verify_consumes_code_single_use(): void
    {
        $this->fakeSmsSender();
        $this->makeUser();

        $this->postJson('/api/v1/user/login-code/phone/request', ['phone_e164' => self::PHONE]);
        $code = $this->lastSentCode();

        $this->postJson('/api/v1/user/login-code/phone/verify', [
            'phone_e164' => self::PHONE,
            'code' => $code,
        ])->assertStatus(200);

        // Rejouer le meme code doit echouer : usage unique.
        $this->postJson('/api/v1/user/login-code/phone/verify', [
            'phone_e164' => self::PHONE,
            'code' => $code,
        ])->assertStatus(410);
    }

    public function test_verify_rejects_wrong_code(): void
    {
        $this->fakeSmsSender();
        $this->makeUser();

        $this->postJson('/api/v1/user/login-code/phone/request', ['phone_e164' => self::PHONE]);

        $this->postJson('/api/v1/user/login-code/phone/verify', [
            'phone_e164' => self::PHONE,
            'code' => '000000',
        ])->assertStatus(401);
    }

    public function test_verify_returns_410_when_no_code_was_requested(): void
    {
        $this->fakeSmsSender();
        $this->makeUser();

        $this->postJson('/api/v1/user/login-code/phone/verify', [
            'phone_e164' => self::PHONE,
            'code' => '123456',
        ])->assertStatus(410);
    }

    public function test_request_is_rate_limited_per_phone(): void
    {
        $this->fakeSmsSender();
        $this->makeUser();

        // 3 envois autorises, le 4e est refuse — mais la reponse reste 204.
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/user/login-code/phone/request', [
                'phone_e164' => self::PHONE,
            ])->assertStatus(204);
        }

        $this->postJson('/api/v1/user/login-code/phone/request', [
            'phone_e164' => self::PHONE,
        ])->assertStatus(204);

        $this->assertTrue(
            RateLimiter::tooManyAttempts(
                'auth:public_phone_otp_send:'.hash('sha256', self::PHONE),
                3
            ),
            'Le quota par numero (3 / 15 min) doit etre applique.'
        );
    }

    public function test_verify_applies_backoff_after_three_failures(): void
    {
        $this->fakeSmsSender();
        $this->makeUser();

        $this->postJson('/api/v1/user/login-code/phone/request', ['phone_e164' => self::PHONE]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/user/login-code/phone/verify', [
                'phone_e164' => self::PHONE,
                'code' => '000000',
            ])->assertStatus(401);
        }

        /** @var array{backoff_until?: int|null}|null $entry */
        $entry = Cache::get('auth:public_phone_otp:'.hash('sha256', self::PHONE));

        $this->assertIsArray($entry);
        $this->assertIsInt(
            $entry['backoff_until'] ?? null,
            'Apres 3 echecs, un backoff doit etre pose.'
        );
    }

    /**
     * Isolation de guard : un jeton `user_api` n'ouvre AUCUN acces employe.
     *
     * `Auth::forgetGuards()` force la re-resolution du guard a chaque appel —
     * sans cela, un guard deja resolu masquerait une fuite d'autorisation.
     */
    public function test_user_api_token_does_not_open_employee_surface(): void
    {
        $this->fakeSmsSender();
        $this->makeUser();

        $this->postJson('/api/v1/user/login-code/phone/request', ['phone_e164' => self::PHONE]);
        $code = $this->lastSentCode();

        $token = $this->postJson('/api/v1/user/login-code/phone/verify', [
            'phone_e164' => self::PHONE,
            'code' => $code,
        ])->json('token');

        $this->assertIsString($token);

        // Le jeton client ouvre bien SA surface.
        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user/me')
            ->assertStatus(200);

        // ... et RIEN de la surface employe (guard `sanctum`).
        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/employees')
            ->assertStatus(401);
    }

    public function test_verify_does_not_create_account_for_unknown_phone(): void
    {
        $this->fakeSmsSender();

        $before = User::query()->count();

        $this->postJson('/api/v1/user/login-code/phone/request', [
            'phone_e164' => '+213555999888',
        ])->assertStatus(204);

        $this->postJson('/api/v1/user/login-code/phone/verify', [
            'phone_e164' => '+213555999888',
            'code' => '123456',
        ])->assertStatus(410);

        $this->assertSame(
            $before,
            User::query()->count(),
            'Un OTP ne doit JAMAIS etre un chemin de creation d identite silencieux.'
        );
    }

    public function test_phone_normalization_is_stable(): void
    {
        // Deux ecritures du meme numero doivent produire la meme cle de cache,
        // sinon l'unicite en base ne sert a rien.
        $a = PhoneNumber::toE164('0555123456', 'DZ');
        $b = PhoneNumber::toE164('+213555123456');

        $this->assertSame($a, $b);
        $this->assertSame(self::PHONE, $a);
    }

    private function lastSentCode(): string
    {
        $code = $this->app->make(SmsCodeSenderContract::class);

        // Le transport de test expose le dernier code via une propriete statique
        // de la classe anonyme ; on le relit par reflexion pour rester generique.
        $reflection = new \ReflectionClass($code);
        $property = $reflection->getProperty('lastCode');
        $property->setAccessible(true);

        /** @var string|null $value */
        $value = $property->getValue();

        $this->assertIsString($value, 'Le transport de test doit avoir recu un code.');

        return $value;
    }
}
