<?php

declare(strict_types=1);

namespace Tests\Feature\LeopardoPublic;

use App\Core\Auth\Domain\Models\User;
use App\Modules\Platform\Domain\Models\PublicActivityIndex;
use App\Modules\Platform\Domain\Models\PublicFavorite;
use App\Modules\Platform\Domain\Models\PublicProfile;
use App\Modules\Platform\Domain\Models\PublicSearchHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * COULOIR 16 — M3-bis · Export des données du client (portabilité RGPD).
 *
 * ## Pourquoi ce fichier existe
 *
 * L'écran Profil de la PWA affiche « bientôt disponible » pour l'export : il
 * ne ment pas, mais l'exigence n'est pas servie. Ce fichier prouve que la
 * route existe, qu'elle rend **les données du client** et **seulement** les
 * siennes, et qu'elle ne divulgue aucun secret.
 *
 * ## Les invariants protégés
 *
 * 1. **Isolation.** Un client n'obtient jamais les données d'un autre.
 * 2. **Aucun secret.** Ni hash de mot de passe, ni jeton d'accès, ni jeton
 *    d'appareil en clair.
 * 3. **Aucune donnée d'un commerçant.** L'export ne contient ni commande, ni
 *    réservation, ni message : ces données appartiennent au professionnel.
 * 4. **Complétude.** Tout ce que le client a fourni est là : compte, profil,
 *    favoris, historique, références d'activité, appareils.
 */
class PublicDataExportTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create([
            'email' => 'client-export@example.test',
            'first_name' => 'Aminata',
            'last_name' => 'Diallo',
            'preferred_language' => 'fr',
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Autorisation
    // ────────────────────────────────────────────────────────────────────────

    public function test_the_export_requires_a_real_user_api_token(): void
    {
        $this->getJson('/api/v1/user/data-export')->assertStatus(401);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Complétude
    // ────────────────────────────────────────────────────────────────────────

    public function test_the_export_contains_every_section_the_client_owns(): void
    {
        $this->actingAsClient();

        $response = $this->getJson('/api/v1/user/data-export')->assertOk();

        foreach ([
            'exported_at',
            'format_version',
            'account',
            'public_profile',
            'favorites',
            'search_history',
            'activity_references',
            'device_tokens',
            'not_included',
        ] as $section) {
            $this->assertArrayHasKey($section, $response->json('data'), "Section `{$section}` absente de l'export.");
        }

        $response->assertJsonPath('data.format_version', '1.0');
    }

    public function test_the_export_returns_the_account_identity(): void
    {
        $this->actingAsClient();

        $this->getJson('/api/v1/user/data-export')
            ->assertOk()
            ->assertJsonPath('data.account.email', 'client-export@example.test')
            ->assertJsonPath('data.account.first_name', 'Aminata')
            ->assertJsonPath('data.account.last_name', 'Diallo');
    }

    public function test_the_export_returns_the_public_profile_when_it_exists(): void
    {
        PublicProfile::query()->create([
            'user_id' => $this->client->id,
            'preferred_locale' => 'ar',
            'approx_city' => 'Ville Fictive',
        ]);

        $this->actingAsClient();

        $this->getJson('/api/v1/user/data-export')
            ->assertOk()
            ->assertJsonPath('data.public_profile.preferred_locale', 'ar')
            ->assertJsonPath('data.public_profile.approx_city', 'Ville Fictive');
    }

    public function test_the_export_returns_favorites_search_history_and_activity_references(): void
    {
        PublicFavorite::query()->create([
            'user_id' => $this->client->id,
            'target_type' => 'restaurant',
            'target_slug' => 'chez-fictif',
        ]);

        PublicSearchHistory::query()->create([
            'user_id' => $this->client->id,
            'query' => 'boulangerie',
            'city' => 'Ville Fictive',
            'result_count' => 3,
            'searched_at' => now(),
        ]);

        PublicActivityIndex::query()->create([
            'user_id' => $this->client->id,
            // `company_id` est NOT NULL dans l'index : c'est le tenant hôte de
            // l'activité. Il est stocké côté serveur mais JAMAIS rendu par
            // l'export (voir l'assertion ci-dessous).
            'company_id' => (string) \Illuminate\Support\Str::uuid(),
            'vertical' => 'restaurant',
            'activity_type' => 'order',
            'external_ref' => 'CMD-0001',
            'status' => 'confirmed',
            'occurred_at' => now(),
        ]);

        $this->actingAsClient();

        $response = $this->getJson('/api/v1/user/data-export')->assertOk();

        $response->assertJsonPath('data.favorites.0.target_slug', 'chez-fictif');
        $response->assertJsonPath('data.search_history.0.query', 'boulangerie');
        $response->assertJsonPath('data.activity_references.0.reference', 'CMD-0001');
        $response->assertJsonPath('data.activity_references.0.status', 'confirmed');

        // L'index porte un `company_id` (tenant hôte) : l'export ne doit pas
        // le divulguer. Le client voit sa référence, pas l'identifiant interne
        // du commerçant.
        $this->assertArrayNotHasKey('company_id', $response->json('data.activity_references.0'));
    }

    // ────────────────────────────────────────────────────────────────────────
    // Aucun secret
    // ────────────────────────────────────────────────────────────────────────

    public function test_the_export_never_contains_a_password_hash_or_an_access_token(): void
    {
        $this->actingAsClient();

        $body = $this->getJson('/api/v1/user/data-export')->assertOk()->getContent();

        $this->assertStringNotContainsString('password', strtolower((string) $body));
        $this->assertStringNotContainsString('remember_token', (string) $body);
        $this->assertStringNotContainsString('plainTextToken', (string) $body);
    }

    public function test_device_tokens_are_truncated_never_returned_in_clear(): void
    {
        $fullToken = 'fcm-TOKEN-SECRET-0123456789abcdef';

        DB::table('public_device_tokens')->insert([
            'user_id' => $this->client->id,
            'token' => $fullToken,
            'platform' => 'android',
            'device_name' => 'Pixel de test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsClient();

        $body = (string) $this->getJson('/api/v1/user/data-export')->assertOk()->getContent();

        // Le jeton complet ne doit JAMAIS apparaître.
        $this->assertStringNotContainsString($fullToken, $body);

        // Mais le client doit pouvoir reconnaître l'appareil.
        $this->assertStringContainsString('abcdef', $body);
        $this->assertStringContainsString('Pixel de test', $body);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Isolation
    // ────────────────────────────────────────────────────────────────────────

    public function test_the_export_never_contains_another_clients_data(): void
    {
        $other = User::factory()->create(['email' => 'autre-export@example.test']);

        PublicFavorite::query()->create([
            'user_id' => $other->id,
            'target_type' => 'restaurant',
            'target_slug' => 'favori-de-quelqu-un-dautre',
        ]);

        PublicSearchHistory::query()->create([
            'user_id' => $other->id,
            'query' => 'recherche-privee-d-autrui',
            'result_count' => 1,
            'searched_at' => now(),
        ]);

        $this->actingAsClient();

        $body = (string) $this->getJson('/api/v1/user/data-export')->assertOk()->getContent();

        $this->assertStringNotContainsString('favori-de-quelqu-un-dautre', $body);
        $this->assertStringNotContainsString('recherche-privee-d-autrui', $body);
        $this->assertStringNotContainsString('autre-export@example.test', $body);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Aucune donnée d'un commerçant
    // ────────────────────────────────────────────────────────────────────────

    public function test_the_export_declares_what_it_does_not_include(): void
    {
        $this->actingAsClient();

        $response = $this->getJson('/api/v1/user/data-export')->assertOk();

        // L'export dit explicitement ce qu'il ne contient pas : un client qui
        // cherche ses commandes ne doit pas croire qu'elles ont disparu.
        $this->assertArrayHasKey('orders_and_reservations', $response->json('data.not_included'));
        $this->assertArrayHasKey('messages', $response->json('data.not_included'));
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helper
    // ────────────────────────────────────────────────────────────────────────

    private function actingAsClient(): void
    {
        Auth::forgetGuards();

        $token = $this->client->createToken('data-export-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token);

        Auth::forgetGuards();
    }
}
