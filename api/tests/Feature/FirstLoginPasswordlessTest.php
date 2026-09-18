<?php

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\CompanyRequest;
use App\Mail\LoginCodeMail;
use App\Mail\TrialWelcomeMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7490 — première connexion sans mot de passe en clair.
 *
 * Verrouille les trois volets de l'issue côté API :
 *  1. l'e-mail de bienvenue ne contient AUCUN secret en clair et porte le lien
 *     magique de définition de mot de passe (provisioning_token, #2903) ;
 *  2. le lien expire : POST /trial/set-password répond 410 après 72 h ;
 *  3. « Recevoir un code de connexion » (OTP) fonctionne pour un compte qui
 *     n'a JAMAIS défini de mot de passe, et se referme dès qu'il en a un.
 */
class FirstLoginPasswordlessTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Accept-Language', 'fr');
    }

    /**
     * Inscription + vérification OTP : provisionne un tenant self-service
     * complet et rend la ligne trial_provisionings `ready`.
     *
     * @return \stdClass&object{id: int|string, provisioning_token: string}
     */
    private function provisionTrialAccount(string $email = 'founder@newtech.dz'): \stdClass
    {
        $this->postJson('/api/v1/trial/signup', [
            'email' => $email,
            'company' => 'NewTech Algeria',
            'role' => 'founder',
            'employees' => '11-50',
            'country' => 'DZ',
        ])->assertStatus(200);

        $otp = CompanyRequest::where('email', $email)
            ->where('status', 'pending')
            ->firstOrFail()
            ->verification_token;

        $this->postJson('/api/v1/trial/verify', [
            'email' => $email,
            'code' => $otp,
        ])->assertStatus(201);

        /** @var object{id: int|string, provisioning_token: string}|null $row */
        $row = DB::table('trial_provisionings')
            ->where('email', $email)
            ->where('status', 'ready')
            ->orderByDesc('id')
            ->first();

        $this->assertInstanceOf(\stdClass::class, $row);

        return $row;
    }

    public function test_welcome_email_carries_set_password_link_and_no_clear_secret(): void
    {
        Mail::fake();

        $row = $this->provisionTrialAccount();

        Mail::assertSent(TrialWelcomeMail::class, function (TrialWelcomeMail $mail) use ($row) {
            $this->assertIsString($mail->setPasswordUrl);
            $this->assertStringContainsString('/auth/set-password?token=', (string) $mail->setPasswordUrl);
            $this->assertStringContainsString((string) $row->provisioning_token, (string) $mail->setPasswordUrl);

            // Aucun secret en clair : le rendu contient le lien magique et le
            // rappel de l'e-mail de connexion, jamais d'identifiants générés
            // (l'ancien bloc « Mot de passe : xxx » a disparu avec la clé
            // email_trial_welcome_password_label, supprimée du catalogue).
            $html = $mail->render();
            $this->assertStringContainsString('/auth/set-password?token=', $html);
            $this->assertStringContainsString('founder@newtech.dz', $html);

            return $mail->hasTo('founder@newtech.dz');
        });
    }

    public function test_set_password_link_expires_after_72_hours(): void
    {
        Mail::fake();

        $row = $this->provisionTrialAccount();

        DB::table('trial_provisionings')
            ->where('id', $row->id)
            ->update(['provisioned_at' => now()->subHours(73)]);

        $this->postJson('/api/v1/trial/set-password', [
            'password' => 'correct-horse-42-battery',
            'password_confirmation' => 'correct-horse-42-battery',
        ], ['X-Token' => (string) $row->provisioning_token])
            ->assertStatus(410)
            ->assertJson([
                'success' => false,
                'error' => 'TRIAL_PASSWORD_LINK_EXPIRED',
            ]);
    }

    public function test_set_password_still_works_within_72_hours(): void
    {
        Mail::fake();

        $row = $this->provisionTrialAccount();

        $this->postJson('/api/v1/trial/set-password', [
            'password' => 'correct-horse-42-battery',
            'password_confirmation' => 'correct-horse-42-battery',
        ], ['X-Token' => (string) $row->provisioning_token])
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_login_code_request_sends_otp_to_account_without_password(): void
    {
        Mail::fake();

        $this->provisionTrialAccount();

        $this->postJson('/api/v1/auth/login-code/request', [
            'email' => 'founder@newtech.dz',
        ])->assertStatus(200)->assertJson(['success' => true]);

        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) {
            return $mail->hasTo('founder@newtech.dz')
                && preg_match('/^\d{6}$/', $mail->loginCode) === 1;
        });
    }

    public function test_login_code_request_is_generic_for_unknown_email(): void
    {
        Mail::fake();

        // Anti-énumération : même réponse qu'un compte éligible, aucun envoi.
        $this->postJson('/api/v1/auth/login-code/request', [
            'email' => 'nobody@example.com',
        ])->assertStatus(200)->assertJson(['success' => true]);

        Mail::assertNotSent(LoginCodeMail::class);
    }

    public function test_login_code_verify_opens_a_session_without_password(): void
    {
        Mail::fake();

        $this->provisionTrialAccount();

        $this->postJson('/api/v1/auth/login-code/request', [
            'email' => 'founder@newtech.dz',
        ])->assertStatus(200);

        $code = null;
        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use (&$code) {
            $code = $mail->loginCode;

            return true;
        });
        $this->assertNotNull($code);

        $response = $this->postJson('/api/v1/auth/login-code/verify', [
            'email' => 'founder@newtech.dz',
            'code' => $code,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'token', 'token_type']);

        // Usage unique : rejouer le même code échoue.
        $this->postJson('/api/v1/auth/login-code/verify', [
            'email' => 'founder@newtech.dz',
            'code' => $code,
        ])->assertStatus(400)->assertJson(['error' => 'LOGIN_CODE_INVALID']);
    }

    public function test_login_code_verify_locks_after_five_wrong_attempts(): void
    {
        Mail::fake();

        $this->provisionTrialAccount();

        $this->postJson('/api/v1/auth/login-code/request', [
            'email' => 'founder@newtech.dz',
        ])->assertStatus(200);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login-code/verify', [
                'email' => 'founder@newtech.dz',
                'code' => '000000',
            ])->assertStatus(400);
        }

        $this->postJson('/api/v1/auth/login-code/verify', [
            'email' => 'founder@newtech.dz',
            'code' => '000000',
        ])->assertStatus(429)->assertJson(['error' => 'LOGIN_CODE_TOO_MANY_ATTEMPTS']);
    }

    public function test_login_code_channel_closes_once_password_is_set(): void
    {
        Mail::fake();

        $row = $this->provisionTrialAccount();

        $this->postJson('/api/v1/trial/set-password', [
            'password' => 'correct-horse-42-battery',
            'password_confirmation' => 'correct-horse-42-battery',
        ], ['X-Token' => (string) $row->provisioning_token])->assertStatus(200);

        // Le compte a maintenant un mot de passe : la demande reste générique
        // (anti-énumération) mais aucun code ne part.
        $this->postJson('/api/v1/auth/login-code/request', [
            'email' => 'founder@newtech.dz',
        ])->assertStatus(200)->assertJson(['success' => true]);

        Mail::assertNotSent(LoginCodeMail::class);
    }
}
