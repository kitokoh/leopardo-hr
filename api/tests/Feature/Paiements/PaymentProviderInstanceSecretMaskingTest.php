<?php

declare(strict_types=1);

namespace Tests\Feature\Paiements;

use App\Core\Tenant\Domain\Enums\PlatformPermission;
use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Paiements\Domain\Enums\ProviderInstanceStatus;
use App\Modules\Paiements\Domain\Models\PaymentProviderInstance;
use App\Modules\Paiements\Infrastructure\Services\Rails\SimulatorRail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * BC-38 PAIEMENTS (#8739) — les identifiants d'une instance ne sortent JAMAIS
 * en clair par l'API de lecture.
 *
 * C'est un critere d'acceptation explicite de l'issue, et il n'etait couvert
 * par aucun test : les ecrans Superadmin affichent des secrets masques, donc la
 * garantie doit etre portee par le serveur, pas par l'interface. Un ecran qui
 * masque un secret que l'API renvoie en clair ne protege rien — la valeur est
 * deja dans la reponse HTTP, lisible dans l'onglet reseau.
 *
 * Trois niveaux sont verifies, du plus profond au plus visible :
 *
 *   1. **At-rest** — la colonne `config` est chiffree : le texte stocke ne
 *      contient pas le secret. C'est ce qui rend le masquage credible plutot
 *      qu'accidentel.
 *   2. **Lecture** — `GET /platform/payments/provider-instances` renvoie un
 *      masque (`••••`) et jamais la valeur, meme apres creation.
 *   3. **Ecriture** — un identifiant envoye a la creation est bien PERSISTE
 *      (le modele le dechiffre) : masquer ne doit pas signifier perdre.
 *
 * Le test ne se contente pas de verifier la reponse : il lit la valeur brute en
 * base, ce qui distingue « masque par l'API » de « jamais stocke ».
 */
final class PaymentProviderInstanceSecretMaskingTest extends TestCase
{
    use CreatesMvpSchema;

    private const SECRET = 'sk_live_8739_do_not_leak_me';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /**
     * Compteur d'unicite : `CreatesMvpSchema` ne rollback PAS entre les tests
     * (il cree/detruit le schema), donc un email fixe violerait
     * `super_admins_email_unique` des le deuxieme test du fichier.
     */
    private static int $accountSequence = 0;

    private function actingAsPlatform(PlatformRole $role): SuperAdmin
    {
        self::$accountSequence++;

        $account = new SuperAdmin([
            'name' => 'Platform '.$role->value,
            'email' => $role->value.'-secrets-8739-'.self::$accountSequence.'@leopardo.test',
        ]);
        $account->forceFill([
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
            'platform_role' => $role->value,
        ])->save();

        Sanctum::actingAs($account, ['*'], 'super_admin_api');

        return $account;
    }

    /**
     * Cree une instance via l'API et retourne son identifiant.
     */
    private function createInstanceWithSecret(): int
    {
        $this->actingAsPlatform(PlatformRole::Finance);

        $response = $this->postJson('/api/v1/platform/payments/provider-instances', [
            'type_code' => SimulatorRail::CODE,
            'display_name' => 'Simulateur (secrets #8739)',
            'countries' => ['DZ'],
            'currencies' => ['DZD'],
            'status' => ProviderInstanceStatus::Active->value,
            'priority' => 10,
            'config' => ['api_key' => self::SECRET],
        ])->assertCreated();

        return (int) $response->json('data.id');
    }

    // ── 1. Chiffrement at-rest ───────────────────────────────────────────────

    public function test_secret_is_encrypted_at_rest_and_not_stored_in_clear(): void
    {
        $id = $this->createInstanceWithSecret();

        // Valeur BRUTE de la colonne, sans passer par le cast du modele.
        $raw = DB::table('payment_provider_instances')->where('id', $id)->value('config');

        self::assertIsString($raw);
        self::assertStringNotContainsString(
            self::SECRET,
            $raw,
            'Le secret ne doit pas apparaitre en clair dans la colonne `config` (#8739).',
        );

        // ...mais il est bien la, dechiffre par le modele : chiffrer n'est pas perdre.
        /** @var PaymentProviderInstance $instance */
        $instance = PaymentProviderInstance::query()->findOrFail($id);
        self::assertSame(self::SECRET, $instance->config['api_key'] ?? null);
    }

    // ── 2. Lecture : masque, jamais la valeur ────────────────────────────────

    public function test_index_never_returns_the_secret_in_clear(): void
    {
        $id = $this->createInstanceWithSecret();

        $this->actingAsPlatform(PlatformRole::Finance);

        $response = $this->getJson('/api/v1/platform/payments/provider-instances')->assertOk();

        // Le corps entier est inspecte : un secret peut fuir par un champ
        // inattendu, pas seulement par `data.*.config.api_key`.
        $body = (string) $response->getContent();

        self::assertStringNotContainsString(
            self::SECRET,
            $body,
            'La reponse de lecture ne doit contenir aucun secret en clair (#8739).',
        );

        $row = collect($response->json('data'))->firstWhere('id', $id);

        self::assertNotNull($row, 'L\'instance creee doit figurer dans la liste.');
        self::assertSame('••••', $row['config']['api_key'] ?? null);
    }

    public function test_created_instance_response_is_already_masked(): void
    {
        $this->actingAsPlatform(PlatformRole::Finance);

        $response = $this->postJson('/api/v1/platform/payments/provider-instances', [
            'type_code' => SimulatorRail::CODE,
            'display_name' => 'Simulateur (reponse de creation)',
            'status' => ProviderInstanceStatus::Active->value,
            'config' => ['api_key' => self::SECRET],
        ])->assertCreated();

        self::assertStringNotContainsString(
            self::SECRET,
            (string) $response->getContent(),
            'Meme la reponse de creation ne doit pas renvoyer le secret (#8739).',
        );

        self::assertSame('••••', $response->json('data.config.api_key'));
    }

    // ── 3. Ecriture : masquer ne doit pas perdre la valeur ───────────────────

    public function test_update_without_credentials_keeps_the_stored_secret(): void
    {
        $id = $this->createInstanceWithSecret();

        $this->actingAsPlatform(PlatformRole::Finance);

        // Mise a jour SANS `config` : c'est le cas de l'ecran, ou les champs
        // d'identifiants laisses vides ne sont pas envoyes.
        $this->patchJson("/api/v1/platform/payments/provider-instances/{$id}", [
            'display_name' => 'Simulateur (renomme)',
            'priority' => 20,
        ])->assertOk();

        /** @var PaymentProviderInstance $instance */
        $instance = PaymentProviderInstance::query()->findOrFail($id);

        self::assertSame('Simulateur (renomme)', $instance->display_name);
        self::assertSame(
            self::SECRET,
            $instance->config['api_key'] ?? null,
            'Une mise a jour sans identifiants ne doit pas effacer le secret en place (#8739).',
        );
    }

    // ── 4. La garde de permission reste en amont ─────────────────────────────

    public function test_role_without_payments_view_cannot_read_instances(): void
    {
        $this->createInstanceWithSecret();

        // Support ne porte pas `payments.view` : la lecture est refusee AVANT
        // toute question de masquage.
        $this->actingAsPlatform(PlatformRole::Support);

        $this->getJson('/api/v1/platform/payments/provider-instances')->assertForbidden();
    }

    public function test_permission_used_by_the_screen_is_the_one_guarding_the_route(): void
    {
        // L'ecran Superadmin (#8739) est declare avec `payments.view` dans
        // `navigation.js`. Si la route changeait de permission, l'entree de
        // menu deviendrait invisible pour les roles qui y ont droit — ou
        // visible pour ceux qui n'y ont pas acces. On verrouille l'alignement.
        self::assertTrue(PlatformRole::Finance->hasPermission(PlatformPermission::PaymentsView));
        self::assertFalse(PlatformRole::Support->hasPermission(PlatformPermission::PaymentsView));
    }
}
