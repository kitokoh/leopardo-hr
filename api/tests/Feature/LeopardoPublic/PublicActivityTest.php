<?php

declare(strict_types=1);

namespace Tests\Feature\LeopardoPublic;

use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Platform\Domain\Models\PublicActivityIndex;
use App\Modules\Platform\Domain\Models\PublicFavorite;
use App\Modules\Platform\Domain\Models\PublicSearchHistory;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Shared\Discovery\PublicActivityLocator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * COULOIR 16 — M3 (#9105) · Activités, favoris et historique de recherche.
 *
 * Couvre les critères d'acceptation de l'issue :
 *
 *   - l'index des activités ne porte AUCUN contenu métier (revue explicite) ;
 *   - un module sans contrat n'apparaît pas dans l'onglet (test) ;
 *   - l'index n'autorise rien : une ligne d'index ne suffit pas à lire une
 *     activité d'un autre client ;
 *   - les favoris sont indexés par client, jamais par tenant ;
 *   - l'historique ne conserve aucune coordonnée ;
 *   - autorisation avec un VRAI token + `Auth::forgetGuards()`.
 *
 * ⚠️ Ces tests s'exécutent sur les VRAIES migrations (`RefreshTenantDatabase`),
 * pas sur une fixture partielle : les colonnes `public_client_id` et les index
 * sont donc réellement vérifiés.
 */
class PublicActivityTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private User $client;

    private User $otherClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'slug' => 'resto-activites',
            'name' => 'Restaurant Activités',
        ]);

        $this->client = User::factory()->create([
            'email' => 'client-activites@example.test',
        ]);

        $this->otherClient = User::factory()->create([
            'email' => 'autre-client@example.test',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Autorisation : vrai token, guards oubliés
    // ─────────────────────────────────────────────────────────────────────

    public function test_activities_require_a_real_user_api_token(): void
    {
        $this->getJson('/api/v1/user/activities')->assertStatus(401);
        $this->getJson('/api/v1/user/favorites')->assertStatus(401);
        $this->getJson('/api/v1/user/search-history')->assertStatus(401);
    }

    public function test_activities_are_returned_for_the_authenticated_client(): void
    {
        $this->actingAsClient($this->client);

        $this->getJson('/api/v1/user/activities')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    // ─────────────────────────────────────────────────────────────────────
    // L'index n'autorise RIEN
    // ─────────────────────────────────────────────────────────────────────

    public function test_an_index_row_alone_does_not_expose_another_clients_activity(): void
    {
        // La commande appartient à `otherClient` dans le schéma du tenant.
        $order = $this->createOrder($this->company, $this->otherClient, 'RST-VOL');

        // Une ligne d'index est écrite pour `client` — scénario d'une donnée
        // corrompue ou d'une alimentation fautive. Elle ne doit RIEN ouvrir.
        PublicActivityIndex::query()->create([
            'user_id' => $this->client->id,
            'company_id' => (string) $this->company->id,
            'vertical' => 'restaurant',
            'activity_type' => PublicActivityIndex::TYPE_ORDER,
            'external_ref' => $order->reference,
            'status' => 'paid',
            'occurred_at' => now(),
        ]);

        $this->actingAsClient($this->client);

        // Le provider relit `public_client_id` dans le tenant : la commande
        // appartient à un autre client, donc la ligne est écartée.
        $this->getJson('/api/v1/user/activities')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_a_verified_activity_is_returned_with_the_tenant_side_status(): void
    {
        $order = $this->createOrder($this->company, $this->client, 'RST-OK');

        // L'index prétend « paid » ; la vérité côté tenant est « open ».
        PublicActivityIndex::query()->create([
            'user_id' => $this->client->id,
            'company_id' => (string) $this->company->id,
            'vertical' => 'restaurant',
            'activity_type' => PublicActivityIndex::TYPE_ORDER,
            'external_ref' => $order->reference,
            'status' => 'paid',
            'occurred_at' => now(),
        ]);

        $this->actingAsClient($this->client);

        $response = $this->getJson('/api/v1/user/activities')->assertOk();

        $response->assertJsonPath('data.0.reference', 'RST-OK');
        // Le statut vient du TENANT, pas de la projection de l'index.
        $response->assertJsonPath('data.0.status', 'open');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Un module sans contrat n'apparaît pas
    // ─────────────────────────────────────────────────────────────────────

    public function test_a_vertical_without_a_contract_does_not_appear_in_sections(): void
    {
        $locator = $this->app->make(PublicActivityLocator::class);

        // `restaurant` a tagué son provider en M3.
        $this->assertTrue($locator->supports('restaurant', 'order'));
        $this->assertTrue($locator->supports('restaurant', 'booking'));

        // Aucune autre verticale n'a de contrat d'activité : elle ne doit pas
        // apparaître, même si une ligne d'index existe pour elle.
        $this->assertFalse($locator->supports('pharmacy', 'order'));
        $this->assertFalse($locator->supports('vtc', 'booking'));
    }

    public function test_an_index_row_for_a_contractless_vertical_is_ignored(): void
    {
        PublicActivityIndex::query()->create([
            'user_id' => $this->client->id,
            'company_id' => (string) $this->company->id,
            'vertical' => 'pharmacy',
            'activity_type' => PublicActivityIndex::TYPE_ORDER,
            'external_ref' => 'PHA-1',
            'status' => 'open',
            'occurred_at' => now(),
        ]);

        $this->actingAsClient($this->client);

        $this->getJson('/api/v1/user/activities')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Favoris : par client, jamais par tenant
    // ─────────────────────────────────────────────────────────────────────

    public function test_favorites_are_scoped_to_the_client(): void
    {
        PublicFavorite::query()->create([
            'user_id' => $this->otherClient->id,
            'target_type' => 'restaurant',
            'target_slug' => 'resto-activites',
        ]);

        $this->actingAsClient($this->client);

        $this->getJson('/api/v1/user/favorites')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_adding_a_favorite_is_idempotent(): void
    {
        $this->actingAsClient($this->client);

        $payload = ['target_type' => 'restaurant', 'target_slug' => 'resto-activites'];

        $this->postJson('/api/v1/user/favorites', $payload)->assertStatus(201);
        // Rejouer l'ajout ne crée pas de doublon.
        $this->postJson('/api/v1/user/favorites', $payload)->assertStatus(200);

        $this->assertSame(
            1,
            PublicFavorite::query()->where('user_id', $this->client->id)->count(),
        );
    }

    public function test_removing_a_favorite_is_idempotent(): void
    {
        $this->actingAsClient($this->client);

        $this->deleteJson('/api/v1/user/favorites/restaurant/resto-activites')
            ->assertStatus(204);
        // Retirer un favori absent n'est pas une erreur.
        $this->deleteJson('/api/v1/user/favorites/restaurant/resto-activites')
            ->assertStatus(204);
    }

    public function test_a_favorite_on_an_unknown_profile_is_refused_uniformly(): void
    {
        $this->actingAsClient($this->client);

        $this->postJson('/api/v1/user/favorites', [
            'target_type' => 'restaurant',
            'target_slug' => 'slug-qui-nexiste-pas',
        ])->assertStatus(404)->assertJsonPath('error', 'PROFILE_NOT_AVAILABLE');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Historique : aucune coordonnée
    // ─────────────────────────────────────────────────────────────────────

    public function test_search_history_is_recorded_and_deduplicated(): void
    {
        $this->actingAsClient($this->client);

        $payload = ['query' => 'pizza', 'city' => 'Alger', 'result_count' => 4];

        $this->postJson('/api/v1/user/search-history', $payload)->assertStatus(204);
        $this->postJson('/api/v1/user/search-history', $payload)->assertStatus(204);

        $this->assertSame(
            1,
            PublicSearchHistory::query()->where('user_id', $this->client->id)->count(),
        );

        $this->getJson('/api/v1/user/search-history')
            ->assertOk()
            ->assertJsonPath('data.0.query', 'pizza')
            ->assertJsonPath('data.0.city', 'Alger');
    }

    public function test_search_history_never_stores_coordinates(): void
    {
        $this->actingAsClient($this->client);

        // Un client qui enverrait des coordonnées ne doit pas les voir
        // persister : la validation les ignore, la table n'a pas de colonne.
        $this->postJson('/api/v1/user/search-history', [
            'query' => 'kebab',
            'city' => 'Oran',
            'latitude' => 35.6971,
            'longitude' => -0.6308,
        ])->assertStatus(204);

        $columns = DB::getSchemaBuilder()->getColumnListing('public.public_search_history');

        $this->assertNotContains('latitude', $columns);
        $this->assertNotContains('longitude', $columns);
    }

    public function test_search_history_can_be_cleared(): void
    {
        $this->actingAsClient($this->client);

        $this->postJson('/api/v1/user/search-history', ['query' => 'tacos'])->assertStatus(204);

        $this->deleteJson('/api/v1/user/search-history')
            ->assertOk()
            ->assertJsonPath('data.deleted', 1);

        $this->assertSame(
            0,
            PublicSearchHistory::query()->where('user_id', $this->client->id)->count(),
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Revue explicite : l'index ne porte aucun contenu métier
    // ─────────────────────────────────────────────────────────────────────

    public function test_the_activity_index_carries_no_business_content(): void
    {
        $columns = DB::getSchemaBuilder()->getColumnListing('public.public_activity_index');

        // Colonnes autorisées : identifiants, routage, projection de statut.
        $allowed = [
            'id', 'user_id', 'company_id', 'vertical', 'activity_type',
            'external_ref', 'status', 'occurred_at', 'created_at', 'updated_at',
        ];

        $this->assertSame(
            [],
            array_values(array_diff($columns, $allowed)),
            'public_activity_index ne doit contenir que des références : aucune colonne métier (montant, panier, adresse, note).',
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Authentifie le client avec un VRAI token Sanctum sur le guard `user_api`.
     *
     * `Auth::forgetGuards()` est indispensable : sans lui, un guard résolu lors
     * d'un test précédent resterait en cache et le test suivant s'exécuterait
     * avec l'identité du précédent — c'est exactement le faux positif que le
     * critère d'acceptation vise.
     */
    private function actingAsClient(User $client): void
    {
        Auth::forgetGuards();

        $token = $client->createToken('m3-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token);

        Auth::forgetGuards();
    }

    /**
     * Crée une commande restaurant réelle dans le schéma du tenant, rattachée
     * au client public donné.
     */
    private function createOrder(Company $company, User $owner, string $reference): RestaurantOrder
    {
        /** @var TenantManager $tenants */
        $tenants = $this->app->make(TenantManager::class);

        return $tenants->withinTenant($company, function () use ($company, $owner, $reference): RestaurantOrder {
            /** @var RestaurantOrder $order */
            $order = RestaurantOrder::query()->create([
                'company_id' => $company->id,
                'reference' => $reference,
                'order_type' => 'takeaway',
                'status' => 'open',
                'subtotal_minor' => 1200,
                'tax_minor' => 0,
                'discount_minor' => 0,
                'total_minor' => 1200,
                'currency' => 'DZD',
                'source' => 'online',
                'public_client_id' => $owner->id,
            ]);

            return $order;
        });
    }
}
