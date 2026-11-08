<?php

declare(strict_types=1);

namespace Tests\Feature\LeopardoPublic;

use App\Core\Auth\Domain\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * COULOIR 16 — M2 (#9104) · Révocation multi-appareils du client grand public.
 *
 * `POST /api/v1/user/logout-all` est le geste « appareil perdu » du contrat
 * (`PUBLIC_ACCOUNT_API_CONTRACT.md` §2.5, cas 8 de la matrice de connexion) :
 * un client qui a perdu un téléphone doit pouvoir révoquer TOUS ses jetons à
 * distance, y compris celui qui porte la requête.
 *
 * ⚠️ Cette route a été livrée par M2 sans aucune couverture dédiée — le seul
 * test qui la mentionnait affirmait qu'elle n'existait PAS (constat d'audit
 * inversé depuis). Ce fichier comble le trou : la révocation est vérifiée
 * fonctionnellement, sur les VRAIES migrations (`RefreshTenantDatabase`), avec
 * un vrai jeton Sanctum et `Auth::forgetGuards()`.
 */
class PublicLogoutAllTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create([
            'email' => 'client-logout-all@example.test',
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Autorisation : vrai token, guards oubliés
    // ────────────────────────────────────────────────────────────────────────

    public function test_logout_all_requires_a_real_user_api_token(): void
    {
        $this->postJson('/api/v1/user/logout-all')->assertStatus(401);
    }

    // ────────────────────────────────────────────────────────────────────────
    // La révocation est TOTALE
    // ────────────────────────────────────────────────────────────────────────

    public function test_logout_all_revokes_every_token_including_the_current_one(): void
    {
        // Trois appareils : deux jetons « autres » et celui qui porte la requête.
        $this->client->createToken('appareil-a');
        $this->client->createToken('appareil-b');

        $this->assertSame(2, $this->client->tokens()->count());

        $this->actingAsClient($this->client);

        $this->postJson('/api/v1/user/logout-all')->assertStatus(204);

        // Aucun jeton ne survit — y compris celui qui vient de servir la requête.
        $this->assertSame(0, $this->client->tokens()->count());
    }

    public function test_a_revoked_token_can_no_longer_reach_a_protected_route(): void
    {
        $this->actingAsClient($this->client);

        // Le jeton fonctionne avant la révocation.
        $this->getJson('/api/v1/user/me')->assertOk();

        $this->postJson('/api/v1/user/logout-all')->assertStatus(204);

        // Le MÊME jeton est désormais refusé : la révocation est effective.
        Auth::forgetGuards();

        $this->getJson('/api/v1/user/me')->assertStatus(401);
    }

    public function test_logout_all_does_not_touch_another_users_tokens(): void
    {
        $other = User::factory()->create([
            'email' => 'autre-logout-all@example.test',
        ]);

        $other->createToken('appareil-autre');

        $this->actingAsClient($this->client);

        $this->postJson('/api/v1/user/logout-all')->assertStatus(204);

        // La révocation est strictement bornée à l'utilisateur authentifié.
        $this->assertSame(1, $other->tokens()->count());
    }

    public function test_logout_all_is_idempotent(): void
    {
        $this->actingAsClient($this->client);

        $this->postJson('/api/v1/user/logout-all')->assertStatus(204);

        // Un second appel sans jeton valide est refusé (401) : la route reste
        // protégée, elle ne devient pas un endpoint ouvert après révocation.
        Auth::forgetGuards();

        $this->postJson('/api/v1/user/logout-all')->assertStatus(401);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────

    private function actingAsClient(User $client): void
    {
        Auth::forgetGuards();

        $token = $client->createToken('logout-all-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token);

        Auth::forgetGuards();
    }
}
