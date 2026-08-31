<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #6530 — surface web super-admin : le login par session doit appliquer
 * les memes gardes que le login API (statut actif + challenge TOTP).
 */
class PlatformWebAuth2faTest extends TestCase
{
    use CreatesMvpSchema;

    private SuperAdmin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->superAdmin = new SuperAdmin([
            'name' => 'Test Super Admin',
            'email' => 'web-2fa@leopardo.test',
        ]);
        $this->superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function loginPost(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        $this->get('/platform/login');
        $token = session()->token();

        return $this->from('/platform/login')->withSession(['_token' => $token])->post('/platform/login', array_merge([
            '_token' => $token,
            'email' => 'web-2fa@leopardo.test',
            'password' => 'password123',
        ], $overrides));
    }

    public function test_active_admin_without_2fa_logs_in_directly(): void
    {
        $this->loginPost()->assertRedirect(route('platform.companies.index'));

        $this->assertAuthenticated('super_admin_web');
    }

    public function test_deactivated_admin_is_rejected_on_web_login(): void
    {
        $this->superAdmin->status = 'deactivated';
        $this->superAdmin->save();

        $response = $this->loginPost();

        $response->assertSessionHasErrors('email');
        $this->assertGuest('super_admin_web');
    }

    public function test_suspended_admin_is_rejected_on_web_login(): void
    {
        $this->superAdmin->status = 'suspended';
        $this->superAdmin->save();

        $this->loginPost()->assertSessionHasErrors('email');

        $this->assertGuest('super_admin_web');
    }

    public function test_admin_with_2fa_must_complete_challenge_before_session(): void
    {
        $this->superAdmin->two_fa_secret = 'TESTSECRET';
        $this->superAdmin->save();

        $this->loginPost()->assertRedirect(route('platform.login.2fa'));

        // Aucune session ouverte tant que le code n'est pas verifie.
        $this->assertGuest('super_admin_web');

        // La page 2FA est accessible et porte l'email.
        $this->get(route('platform.login.2fa'))
            ->assertOk()
            ->assertSee('Verification a deux facteurs')
            ->assertSee('value="web-2fa@leopardo.test"', false);
    }

    public function test_admin_with_2fa_rejected_on_wrong_code(): void
    {
        $this->superAdmin->two_fa_secret = 'TESTSECRET';
        $this->superAdmin->save();

        $this->loginPost()->assertRedirect(route('platform.login.2fa'));

        $this->get(route('platform.login.2fa'));
        $token = session()->token();

        $this->from(route('platform.login.2fa'))->withSession(['_token' => $token])->post(route('platform.login.2fa.verify'), [
            '_token' => $token,
            'email' => 'web-2fa@leopardo.test',
            'code' => '123456', // code invalide
        ])->assertSessionHasErrors('code');

        $this->assertGuest('super_admin_web');
    }

    public function test_admin_with_2fa_logs_in_after_valid_code(): void
    {
        $this->superAdmin->two_fa_secret = 'TESTSECRET';
        $this->superAdmin->save();

        $this->loginPost()->assertRedirect(route('platform.login.2fa'));

        $this->get(route('platform.login.2fa'));
        $token = session()->token();

        // Code valide : le verifier tourne avec l'implementation TOTP de
        // SuperAdminService (fenetre -1..+1) — on emprunte la meme logique en
        // generant un code pour l'horodatage courant.
        $code = $this->validTotp('TESTSECRET');

        $this->from(route('platform.login.2fa'))->withSession(['_token' => $token])->post(route('platform.login.2fa.verify'), [
            '_token' => $token,
            'email' => 'web-2fa@leopardo.test',
            'code' => $code,
        ])->assertRedirect(route('platform.companies.index'));

        $this->assertAuthenticated('super_admin_web');
    }

    public function test_2fa_page_without_pending_state_redirects_to_login(): void
    {
        $this->get(route('platform.login.2fa'))->assertRedirect(route('platform.login'));
    }

    public function test_2fa_verify_without_pending_state_redirects_to_login(): void
    {
        $this->get('/platform/login');
        $token = session()->token();

        $this->from(route('platform.login.2fa'))->withSession(['_token' => $token])->post(route('platform.login.2fa.verify'), [
            '_token' => $token,
            'email' => 'web-2fa@leopardo.test',
            'code' => '000000',
        ])->assertRedirect(route('platform.login'));
    }

    /**
     * Genere un code TOTP valide (meme algorithme que SuperAdminService :
     * TOTP RFC 6238 SHA1, 6 chiffres, pas de 30 s, fenetre courante).
     */
    private function validTotp(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';

        foreach (str_split(strtoupper(rtrim($secret, '='))) as $char) {
            $bits .= str_pad(decbin($alphabet[$char]), 5, '0', STR_PAD_LEFT);
        }

        $key = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $key .= chr(bindec($chunk));
            }
        }

        $counter = intdiv(time(), 30);
        $hash = hash_hmac('sha1', pack('N2', 0, $counter), $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
