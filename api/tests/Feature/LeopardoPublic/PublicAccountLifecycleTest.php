<?php

declare(strict_types=1);

namespace Tests\Feature\LeopardoPublic;

use App\Core\Auth\Domain\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * COULOIR 16 — M2 (#9104) · Cycle de vie du compte public : `refresh` et
 * `DELETE /account`.
 *
 * ## Pourquoi ce fichier existe
 *
 * `PublicAccountController` porte trois opérations que le contrat
 * `PUBLIC_ACCOUNT_API_CONTRACT.md` §2.4–2.6 impose. Seule `logout-all` avait
 * une couverture (`PublicLogoutAllTest`). **`POST /user/refresh` et
 * `DELETE /user/account` n'avaient AUCUN test** — vérifié par recherche plein
 * texte des routes dans `tests/`, 0 occurrence.
 *
 * Ce n'est pas anodin : ce sont les deux opérations les plus dangereuses du
 * cycle de vie.
 *  - `refresh` mal implémenté = deux jetons valides (fenêtre de réutilisation),
 *    donc un jeton volé survit à la rotation ;
 *  - `DELETE /account` mal implémenté = suppression immédiate et irréversible,
 *    ou suppression d'un compte sans ré-authentification (un jeton volé suffit).
 *
 * Les tests ci-dessous sont exécutés sur les VRAIES migrations
 * (`RefreshTenantDatabase`), avec de vrais jetons Sanctum.
 *
 * ## Ce que ces tests ne prétendent PAS prouver
 *
 * L'envoi réel de l'e-mail de confirmation et l'annulation de la suppression
 * à J+30 relèvent du lot de purge (L1/L4). Ici : le marquage, la planification,
 * la déconnexion immédiate et les refus.
 */
class PublicAccountLifecycleTest extends TestCase
{
    use RefreshTenantDatabase;

    private const PASSWORD = 'MotDePasse!2026';

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create([
            'email' => 'client-lifecycle@example.test',
            'password_hash' => Hash::make(self::PASSWORD),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /user/refresh — rotation à fenêtre de grâce 0
    // ─────────────────────────────────────────────────────────────────────────

    public function test_refresh_requires_a_real_user_api_token(): void
    {
        // Sans jeton, la route est protégée : elle ne doit pas ouvrir un compte
        // au passage. Un 401 ici, pas un 500.
        $this->postJson('/api/v1/user/refresh')->assertStatus(401);
    }

    public function test_refresh_returns_a_new_token_and_the_old_one_dies(): void
    {
        $oldPlain = $this->actingAsClient();

        $response = $this->postJson('/api/v1/user/refresh');

        $response->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'token_expires_at']);

        $newPlain = (string) $response->json('token');

        // Le nouveau jeton est bien un jeton différent, exploitable.
        $this->assertNotSame($oldPlain, $newPlain);
        $this->assertSame('Bearer', $response->json('token_type'));

        // L'ANCIEN jeton doit être mort : c'est la définition d'une rotation
        // à fenêtre de grâce 0. S'il survivait, un jeton volé continuerait de
        // fonctionner après que la victime a « rafraîchi ».
        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$oldPlain);
        Auth::forgetGuards();
        $this->getJson('/api/v1/user/me')->assertStatus(401);

        // Le nouveau, lui, ouvre bien la surface protégée.
        $this->withHeader('Authorization', 'Bearer '.$newPlain);
        Auth::forgetGuards();
        $this->getJson('/api/v1/user/me')->assertOk();
    }

    public function test_refresh_does_not_multiply_tokens(): void
    {
        // Deux jetons au départ (deux appareils). Une rotation n'en ajoute pas :
        // elle en remplace un.
        $this->client->createToken('appareil-b');
        $this->actingAsClient();

        $before = $this->client->tokens()->count();
        $this->assertSame(2, $before);

        $this->postJson('/api/v1/user/refresh')->assertOk();

        $this->assertSame($before, $this->client->tokens()->count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE /user/account — suppression différée, ré-authentifiée
    // ─────────────────────────────────────────────────────────────────────────

    public function test_account_deletion_requires_a_real_user_api_token(): void
    {
        $this->deleteJson('/api/v1/user/account', [
            'password' => self::PASSWORD,
            'confirmation' => 'SUPPRIMER',
        ])->assertStatus(401);
    }

    public function test_account_deletion_without_the_right_password_is_refused_and_changes_nothing(): void
    {
        $this->actingAsClient();

        $this->deleteJson('/api/v1/user/account', [
            'password' => 'mauvais-mot-de-passe',
            'confirmation' => 'SUPPRIMER',
        ])->assertStatus(401)
            ->assertJsonPath('error', 'INVALID_CREDENTIALS');

        // Rien ne doit avoir bougé : ni marquage, ni purge planifiée, ni jeton
        // révoqué. Un refus qui laisse des traces est un refus raté.
        $fresh = $this->client->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->deletion_requested_at);
        $this->assertNull($fresh->purge_scheduled_at);
        $this->assertSame(1, $fresh->tokens()->count());
    }

    public function test_account_deletion_without_the_confirmation_word_is_refused(): void
    {
        $this->actingAsClient();

        $this->deleteJson('/api/v1/user/account', [
            'password' => self::PASSWORD,
            'confirmation' => 'peut-etre',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'CONFIRMATION_REQUIRED');

        $fresh = $this->client->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->purge_scheduled_at);
        $this->assertSame(1, $fresh->tokens()->count());
    }

    public function test_account_deletion_is_deferred_thirty_days_and_reversible(): void
    {
        $this->actingAsClient();

        $response = $this->deleteJson('/api/v1/user/account', [
            'password' => self::PASSWORD,
            'confirmation' => 'SUPPRIMER',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('status', 'pending');

        $scheduled = $response->json('purge_scheduled_at');
        $this->assertNotNull($scheduled);

        // Le compte est MARQUÉ, mais la ligne `users` existe encore : la
        // suppression est différée, pas immédiate. Une purge immédiate serait
        // irréversible et contredirait le contrat.
        $fresh = $this->client->fresh();
        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->deletion_requested_at);
        $this->assertNotNull($fresh->purge_scheduled_at);

        // 30 jours de réversibilité, mesurés depuis le marquage réel et non
        // depuis `now()` du test : quelques millisecondes s'écoulent entre les
        // deux, et `diffInDays` tronque — 29,99 jours donnerait 29. On compare
        // donc la date planifiée à `deletion_requested_at + 30 jours`, ce qui
        // est exact quelle que soit l'heure d'exécution.
        $expected = \Illuminate\Support\Carbon::parse($fresh->deletion_requested_at)->addDays(30);
        $this->assertSame(
            $expected->toDateTimeString(),
            \Illuminate\Support\Carbon::parse($fresh->purge_scheduled_at)->toDateTimeString(),
        );

        // Toutes les sessions sont coupées immédiatement : un compte en cours
        // de suppression ne doit plus être utilisable.
        $this->assertSame(0, $fresh->tokens()->count());
    }

    public function test_account_deletion_revokes_every_device_not_just_the_current_one(): void
    {
        $this->client->createToken('appareil-perdu');
        $this->client->createToken('tablette');
        $this->actingAsClient();

        $this->assertSame(3, $this->client->tokens()->count());

        $this->deleteJson('/api/v1/user/account', [
            'password' => self::PASSWORD,
            'confirmation' => 'SUPPRIMER',
        ])->assertStatus(202);

        $fresh = $this->client->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(0, $fresh->tokens()->count());
    }

    public function test_account_deletion_never_touches_another_account(): void
    {
        $other = User::factory()->create(['email' => 'autre-client@example.test']);
        $other->createToken('son-appareil');

        $this->actingAsClient();
        $this->deleteJson('/api/v1/user/account', [
            'password' => self::PASSWORD,
            'confirmation' => 'SUPPRIMER',
        ])->assertStatus(202);

        // Le voisin est intact : ni marqué, ni déconnecté.
        $freshOther = $other->fresh();
        $this->assertNotNull($freshOther);
        $this->assertNull($freshOther->deletion_requested_at);
        $this->assertSame(1, $freshOther->tokens()->count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Authentifie une requête avec un vrai jeton Sanctum de `user_api`.
     *
     * @return string le jeton en clair, pour pouvoir vérifier ensuite qu'il meurt
     */
    private function actingAsClient(): string
    {
        Auth::forgetGuards();

        $token = $this->client->createToken('lifecycle-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token);

        Auth::forgetGuards();

        return $token;
    }
}
