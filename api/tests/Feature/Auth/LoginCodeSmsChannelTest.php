<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Contracts\SmsCodeSenderContract;
use App\Core\Auth\Infrastructure\Sms\LogOnlySmsCodeSender;
use App\Mail\LoginCodeMail;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8738 — canal SMS du code de connexion a usage unique.
 *
 * Ces tests verifient les proprietes qui ne dependent PAS d'un fournisseur SMS
 * reel, c'est-a-dire toutes celles qui peuvent regresser en silence :
 *
 *  1. le canal se declare **non configure** par defaut, et l'appelant replit
 *     alors sur l'e-mail (un code ne doit jamais partir dans le vide) ;
 *  2. le numero est **valide en E.164** avant tout envoi — un numero douteux
 *     n'est jamais « repare » au mieux ;
 *  3. le quota par numero est **applique** (3 / 15 min) ;
 *  4. **aucune PII dans les traces** : ni le code, ni le numero complet.
 *
 * Le point 4 est le critere d'acceptation explicite de l'issue : « le code
 * n'apparait jamais dans les logs ». Il est teste en interceptant le logger.
 */
final class LoginCodeSmsChannelTest extends TestCase
{
    // `trial_provisionings` vit dans le schema `public` : sans ce trait, la
    // table n'existe pas dans la base de test et les deux tests d'integration
    // du repli echouaient en 42P01 (relation inconnue).
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('auth:login_code_sms:'.hash('sha256', '+213555123456'));
        RateLimiter::clear('auth:login_code_sms:'.hash('sha256', '+213555999888'));
    }

    public function test_sms_channel_is_not_configured_by_default(): void
    {
        config(['services.sms_otp.endpoint' => '', 'services.sms_otp.api_key' => '']);

        $this->assertFalse(
            (new LogOnlySmsCodeSender)->isConfigured(),
            'Sans endpoint ni cle, le canal DOIT se declarer non configure : sinon le controleur ne peut pas replier sur l\'e-mail.'
        );
    }

    public function test_sms_channel_requires_both_endpoint_and_key(): void
    {
        // Un endpoint sans cle est une configuration a moitie faite : elle doit
        // compter comme NON configuree, pas comme prete.
        config(['services.sms_otp.endpoint' => 'https://sms.example.com/send', 'services.sms_otp.api_key' => '']);
        $this->assertFalse((new LogOnlySmsCodeSender)->isConfigured());

        config(['services.sms_otp.endpoint' => '', 'services.sms_otp.api_key' => 'secret']);
        $this->assertFalse((new LogOnlySmsCodeSender)->isConfigured());

        config(['services.sms_otp.endpoint' => 'https://sms.example.com/send', 'services.sms_otp.api_key' => 'secret']);
        $this->assertTrue((new LogOnlySmsCodeSender)->isConfigured());
    }

    public function test_invalid_e164_is_rejected_without_sending(): void
    {
        config(['services.sms_otp.endpoint' => 'https://sms.example.com/send', 'services.sms_otp.api_key' => 'secret']);

        // Saisie nationale non normalisee : le contrat prend un E.164, donc on
        // refuse plutot que de deviner le pays.
        $this->assertNull(
            (new LogOnlySmsCodeSender)->send('0555123456', '123456', 'fr'),
            'Un numero non-E.164 ne doit jamais etre accepte par le canal.'
        );

        // Et le helper de normalisation confirme que cette saisie ne peut pas
        // etre tranchee sans pays.
        $this->assertNull(PhoneNumber::toE164('0555123456'));
    }

    public function test_rate_limit_blocks_the_fourth_send_for_the_same_number(): void
    {
        config(['services.sms_otp.endpoint' => 'https://sms.example.com/send', 'services.sms_otp.api_key' => 'secret']);

        $sender = new LogOnlySmsCodeSender;
        $phone = '+213555123456';

        $traces = [];
        for ($i = 0; $i < 3; $i++) {
            $traces[] = $sender->send($phone, '123456', 'fr');
        }

        $this->assertNotContains(null, $traces, 'Les 3 premiers envois doivent passer.');

        $this->assertNull(
            $sender->send($phone, '123456', 'fr'),
            'Le 4e envoi dans la fenetre doit etre refuse : sans quota, un numero est enumerable.'
        );
    }

    public function test_rate_limit_is_per_number_not_global(): void
    {
        config(['services.sms_otp.endpoint' => 'https://sms.example.com/send', 'services.sms_otp.api_key' => 'secret']);

        $sender = new LogOnlySmsCodeSender;

        for ($i = 0; $i < 3; $i++) {
            $sender->send('+213555123456', '123456', 'fr');
        }

        $this->assertNotNull(
            $sender->send('+213555999888', '123456', 'fr'),
            'Un numero epuise ne doit pas bloquer les autres utilisateurs.'
        );
    }

    public function test_logs_never_contain_the_code_nor_the_full_number(): void
    {
        config(['services.sms_otp.endpoint' => '', 'services.sms_otp.api_key' => '']);

        $captured = [];
        Log::listen(function ($record) use (&$captured): void {
            $captured[] = $record->message.' '.json_encode($record->context);
        });

        $code = '987654';
        $phone = '+213555123456';

        (new LogOnlySmsCodeSender)->send($phone, $code, 'fr');

        $this->assertNotEmpty($captured, 'Le mode audit-only doit au moins tracer le FAIT de la demande.');
        $joined = implode("\n", $captured);

        $this->assertStringNotContainsString($code, $joined, 'Le code ne doit JAMAIS apparaitre dans les logs (#8738).');
        $this->assertStringNotContainsString('555123456', $joined, 'Le numero complet ne doit jamais apparaitre : il est masque.');
        $this->assertStringContainsString('2135', $joined, 'On garde l\'indicatif : assez pour investiguer, pas pour recomposer.');
    }

    public function test_request_falls_back_to_email_when_sms_is_not_configured(): void
    {
        Mail::fake();
        config(['services.sms_otp.endpoint' => '', 'services.sms_otp.api_key' => '']);
        $this->seedProvisioning('fallback@example.test', '+213555123456');

        $response = $this->postJson('/api/v1/auth/login-code/request', [
            'email' => 'fallback@example.test',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        // `LoginCodeMail` est `ShouldQueue` (#7854) : `Mail::fake()` l'EMPILE,
        // donc l'assertion correcte est `assertQueued`, pas `assertSent`.
        Mail::assertQueued(LoginCodeMail::class, 1);
    }

    public function test_request_uses_sms_when_configured_and_skips_email(): void
    {
        Mail::fake();
        config(['services.sms_otp.endpoint' => 'https://sms.example.com/send', 'services.sms_otp.api_key' => 'secret']);
        $this->seedProvisioning('sms@example.test', '+213555999888');

        $this->app->instance(SmsCodeSenderContract::class, new class implements SmsCodeSenderContract
        {
            public function channel(): string
            {
                return 'sms';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            // Type de retour resserre a `string` (covariance) : cet anonyme
            // reussit toujours, donc PHPStan signale un `?string` inutile.
            public function send(string $e164, string $code, string $locale): string
            {
                return 'trace_test';
            }
        });

        $response = $this->postJson('/api/v1/auth/login-code/request', [
            'email' => 'sms@example.test',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }

    public function test_request_falls_back_to_email_when_sms_sender_returns_null(): void
    {
        Mail::fake();
        config(['services.sms_otp.endpoint' => 'https://sms.example.com/send', 'services.sms_otp.api_key' => 'secret']);
        $this->seedProvisioning('nofallback@example.test', '+213555123456');

        // Le canal se dit configure mais l'envoi echoue : rien n'est parti,
        // donc un code doit encore partir par e-mail.
        $this->app->instance(SmsCodeSenderContract::class, new class implements SmsCodeSenderContract
        {
            public function channel(): string
            {
                return 'sms';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function send(string $e164, string $code, string $locale): ?string
            {
                return null;
            }
        });

        $response = $this->postJson('/api/v1/auth/login-code/request', [
            'email' => 'nofallback@example.test',
        ]);

        $response->assertOk();
        // `LoginCodeMail` est `ShouldQueue` (#7854) : `Mail::fake()` l'EMPILE,
        // donc l'assertion correcte est `assertQueued`, pas `assertSent`.
        Mail::assertQueued(LoginCodeMail::class, 1);
    }

    private function seedProvisioning(string $email, string $phone): void
    {
        DB::table('trial_provisionings')->insert([
            'email' => $email,
            'phone_e164' => $phone,
            'provisioning_token' => 'tok_'.md5($email),
            'status' => 'ready',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
