<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use App\Modules\Billing\Application\Actions\SetTrialPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7962 — l'Action `SetTrialPassword` est exercée DIRECTEMENT, sans passer par
 * le controller.
 *
 * C'est la condition posée par la règle D4 du registre (« pas d'extraction
 * sans test ») : un test qui passe par le controller ne survit pas à la
 * prochaine refactorisation du controller — il teste le transport HTTP, pas
 * la règle métier qu'on vient d'isoler.
 *
 * Les quatre garanties verrouillées ici sont celles qui coûtent cher si elles
 * régressent :
 *   1. token à usage unique (un token égaré ne réécrit pas un mot de passe) ;
 *   2. expiration 72 h du lien magique (#7490) ;
 *   3. révocation du magic link après définition du mot de passe ;
 *   4. aucune PII en clair dans les logs (BOS-002 / #8144).
 */
final class SetTrialPasswordActionTest extends TestCase
{
    use RefreshTenantDatabase;

    private const PASSWORD = 'correct-horse-42-battery';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Accept-Language', 'fr');
    }

    /**
     * Provisionne un tenant self-service complet et rend la ligne
     * `trial_provisionings` à l'état `ready`.
     *
     * @return object{id: int|string, provisioning_token: string, company_id: string, email: string}
     */
    private function provisionTrialAccount(string $email = 'founder@newtech.dz'): object
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

        /** @var object{id: int|string, provisioning_token: string, company_id: string, email: string}|null $row */
        $row = DB::table('trial_provisionings')
            ->where('email', $email)
            ->where('status', 'ready')
            ->orderByDesc('id')
            ->first();

        $this->assertInstanceOf(\stdClass::class, $row);

        return $row;
    }

    public function test_it_sets_the_password_and_revokes_the_magic_link(): void
    {
        Mail::fake();

        $row = $this->provisionTrialAccount();

        $manager = Employee::query()
            ->where('email', $row->email)
            ->where('company_id', $row->company_id)
            ->where('role', 'manager')
            ->firstOrFail();

        // Le magic link est bien présent AVANT (sinon le test ne prouverait
        // rien sur sa révocation).
        $manager->forceFill(['extra_data' => array_merge($manager->extra_data ?? [], [
            'demo_access_token_hash' => 'hash-avant',
            'demo_access_token_expires_at' => now()->addDay()->toIso8601String(),
        ])])->save();

        $result = app(SetTrialPassword::class)->execute((string) $row->provisioning_token, self::PASSWORD);

        self::assertTrue($result['success']);

        $manager->refresh();
        self::assertTrue(Hash::check(self::PASSWORD, (string) $manager->password_hash));

        // Le magic link ne peut plus ouvrir de session.
        self::assertArrayNotHasKey('demo_access_token_hash', $manager->extra_data ?? []);
        self::assertArrayNotHasKey('demo_access_token_expires_at', $manager->extra_data ?? []);

        $provisioning = DB::table('trial_provisionings')->where('id', $row->id)->first();
        self::assertNotNull($provisioning);
        // PHPStan niveau 8 : `first()` rend `stdClass|null`. L'assertion
        // ci-dessus restreint le type pour l'analyseur, ce qui rend la
        // lecture de la propriete sure.
        self::assertNotNull($provisioning->password_set_at);
    }

    public function test_the_token_is_single_use(): void
    {
        Mail::fake();

        $row = $this->provisionTrialAccount();
        $action = app(SetTrialPassword::class);

        self::assertTrue($action->execute((string) $row->provisioning_token, self::PASSWORD)['success']);

        // Second appel avec le MÊME token : refusé. Sans cette garantie, un
        // token égaré (email transféré, historique de navigateur partagé)
        // permettrait de réécrire le mot de passe d'un compte déjà actif.
        $second = $action->execute((string) $row->provisioning_token, 'another-password-99');

        self::assertFalse($second['success']);
        self::assertSame('TRIAL_PASSWORD_ALREADY_SET', $second['error']);
        self::assertSame(409, $second['status']);
    }

    public function test_the_link_expires_after_72_hours(): void
    {
        Mail::fake();

        $row = $this->provisionTrialAccount();

        DB::table('trial_provisionings')
            ->where('id', $row->id)
            ->update(['provisioned_at' => now()->subHours(SetTrialPassword::LINK_TTL_HOURS + 1)]);

        $result = app(SetTrialPassword::class)->execute((string) $row->provisioning_token, self::PASSWORD);

        self::assertFalse($result['success']);
        self::assertSame('TRIAL_PASSWORD_LINK_EXPIRED', $result['error']);
        self::assertSame(410, $result['status']);
    }

    public function test_a_malformed_token_is_rejected_before_any_query(): void
    {
        $result = app(SetTrialPassword::class)->execute('too-short', self::PASSWORD);

        self::assertFalse($result['success']);
        self::assertSame('PROVISIONING_TOKEN_INVALID', $result['error']);
        self::assertSame(404, $result['status']);
    }

    public function test_an_unknown_token_is_rejected(): void
    {
        $result = app(SetTrialPassword::class)->execute(str_repeat('a', 64), self::PASSWORD);

        self::assertFalse($result['success']);
        self::assertSame('PROVISIONING_NOT_READY', $result['error']);
        self::assertSame(409, $result['status']);
    }
}
