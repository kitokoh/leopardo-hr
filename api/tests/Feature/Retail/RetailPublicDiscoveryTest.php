<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Models\RetailOnlineSettings;
use App\Shared\Contracts\Discovery\PublicDiscoverableProvider;
use App\Shared\Discovery\PublicDiscoveryService;
use App\Shared\Discovery\PublicProfileLocator;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * C-1 (#8773) — Boutique Leopardo Marché exposée sur la passerelle publique.
 *
 * Vérifie que le provider branché sur `RetailMarketplaceService` respecte les
 * invariants du contrat `PublicDiscoverableProvider` :
 *
 *  - **fail-closed** : seules les boutiques opt-in (`retail_online_settings.
 *    enabled`) dont la feature `retail` est active et la société saine sont
 *    exposées ;
 *  - **DTO public strict** : aucun `company_id`, aucun stock, aucune marge, et
 *    surtout **aucune coordonnée de contact** (`contact_phone`/`contact_email`
 *    de la table de réglages ne sont pas publiées par défaut) ;
 *  - **cohérence découverte / résolution** : une boutique dépubliée disparaît
 *    de la recherche ET cesse d'être joignable ;
 *  - **actions réelles** : `contact` + `order` (la commande en ligne est
 *    réellement servie par `POST /public/market/orders`), pas de `booking`.
 */
class RetailPublicDiscoveryTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $settings
     */
    private function shop(
        string $slug,
        string $shopName,
        bool $featureEnabled = true,
        bool $enabled = true,
        array $attributes = [],
        array $settings = [],
    ): Company {
        /** @var Company $company */
        $company = Company::factory()->create(array_merge([
            'slug' => $slug,
            'name' => 'Société '.$shopName,
            'country' => 'DZ',
            'currency' => 'DZD',
        ], $attributes));

        if ($featureEnabled) {
            $company->setFeature('retail', true);
            $company->save();
        }

        RetailOnlineSettings::query()->create(array_merge([
            'company_id' => (string) $company->id,
            'enabled' => $enabled,
            'shop_name' => $shopName,
            'currency' => 'DZD',
            'version' => 1,
        ], $settings));

        return $company;
    }

    public function test_provider_is_registered_on_the_public_gateway(): void
    {
        /** @var PublicDiscoveryService $discovery */
        $discovery = app(PublicDiscoveryService::class);

        $this->assertContains('retail_shop', $discovery->types(), 'le provider Retail doit être tagué `public.discoverable`');
    }

    public function test_resolver_is_registered_and_announces_order_and_contact(): void
    {
        /** @var PublicDiscoveryService $discovery */
        $discovery = app(PublicDiscoveryService::class);
        /** @var PublicProfileLocator $locator */
        $locator = app(PublicProfileLocator::class);

        $this->assertContains('retail_shop', $locator->types(), 'le resolver Retail doit être tagué `public.profile_resolver`');

        $actions = $discovery->actionsByType()['retail_shop'] ?? [];

        $this->assertSame(['contact', 'order'], $actions, 'la commande en ligne est servie, la réservation ne l\'est pas');
    }

    public function test_only_optin_active_shops_are_discoverable(): void
    {
        $this->shop('boutique-alpha', 'Boutique Alpha');
        // Réglages créés mais `enabled = false` → exclue.
        $this->shop('boutique-fermee', 'Boutique Fermée', enabled: false);
        // Opt-in mais feature `retail` coupée → exclue (kill switch).
        $this->shop('boutique-sans-flag', 'Boutique Sans Flag', featureEnabled: false);
        // Société suspendue → exclue même avec un opt-in actif.
        $this->shop('boutique-suspendue', 'Boutique Suspendue', attributes: ['status' => 'suspended']);

        $response = $this->getJson('/api/v1/public/discover?type=retail_shop')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response->assertJsonPath('data.0.type', 'retail_shop');
        $response->assertJsonPath('data.0.slug', 'boutique-alpha');
        $response->assertJsonPath('data.0.name', 'Boutique Alpha');
    }

    public function test_public_item_never_leaks_internal_data(): void
    {
        $company = $this->shop('boutique-alpha', 'Boutique Alpha', settings: [
            'contact_phone' => '+213555000111',
            'contact_email' => 'prive@boutique.test',
            'shop_description' => 'Épicerie fine',
        ]);

        $response = $this->getJson('/api/v1/public/discover?type=retail_shop')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $item = $response->json('data.0');

        $this->assertArrayNotHasKey('company_id', $item);
        $this->assertArrayNotHasKey('id', $item);
        $this->assertArrayNotHasKey('contact_phone', $item);
        $this->assertArrayNotHasKey('contact_email', $item);
        $this->assertArrayNotHasKey('currency', $item);
        $this->assertArrayNotHasKey('version', $item);

        // Les coordonnées privées ne doivent apparaître NULLE PART dans la réponse.
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('+213555000111', $body);
        $this->assertStringNotContainsString('prive@boutique.test', $body);
        $this->assertStringNotContainsString((string) $company->id, $body);

        // La description publique, elle, est bien publiée.
        $this->assertSame('Épicerie fine', $item['description']);
    }

    public function test_search_filters_by_shop_name_and_city(): void
    {
        $this->shop('boutique-alpha', 'Boutique Alpha', settings: ['city' => 'Alger']);
        $this->shop('boutique-beta', 'Boutique Beta', settings: ['city' => 'Oran']);

        $this->getJson('/api/v1/public/discover?type=retail_shop&q=alpha')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'boutique-alpha');

        $this->getJson('/api/v1/public/discover?type=retail_shop&city=Oran')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'boutique-beta');
    }

    public function test_resolver_returns_owner_tenant_and_prefers_the_public_shop_name(): void
    {
        $company = $this->shop('boutique-alpha', 'Boutique Alpha');

        /** @var PublicProfileLocator $locator */
        $locator = app(PublicProfileLocator::class);

        $resolved = $locator->locate('retail_shop', 'boutique-alpha');

        $this->assertIsArray($resolved);
        $this->assertSame((string) $company->id, $resolved['company_id']);
        $this->assertSame('Boutique Alpha', $resolved['display_name'], 'le nom public est celui de la boutique, pas la raison sociale');
    }

    public function test_resolver_is_fail_closed_when_shop_is_opted_out(): void
    {
        $company = $this->shop('boutique-alpha', 'Boutique Alpha');

        /** @var PublicProfileLocator $locator */
        $locator = app(PublicProfileLocator::class);

        $this->assertNotNull($locator->locate('retail_shop', 'boutique-alpha'));

        RetailOnlineSettings::query()
            ->where('company_id', (string) $company->id)
            ->update(['enabled' => false]);

        $this->assertNull($locator->locate('retail_shop', 'boutique-alpha'), 'une boutique retirée de la marketplace n\'est plus joignable');
        $this->getJson('/api/v1/public/discover?type=retail_shop')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_contact_request_accepts_the_new_public_type(): void
    {
        $this->shop('boutique-alpha', 'Boutique Alpha', settings: ['city' => 'Alger']);

        $this->postJson('/api/v1/public/contact-requests', [
            'target_type' => 'retail_shop',
            'target_slug' => 'boutique-alpha',
            'sender_name' => 'Client Test',
            'sender_email' => 'client@example.test',
            'message' => 'Bonjour, avez-vous ce produit en stock en magasin ?',
            'consent' => true,
            'idempotency_key' => 'idem-retail-'.str_repeat('d', 8),
        ])->assertCreated();
    }

    /**
     * P1-02 — l'item de découverte doit pointer vers une fiche publique qui
     * EXISTE réellement. Avant ce lot, `url` valait `null` : un résultat Retail
     * n'avait aucune fiche profil et renvoyait vers un parcours d'achat.
     *
     * Le test ne se contente pas de vérifier la chaîne : il APPELLE la route
     * cible, donc un `url` pointant vers une route inexistante échoue ici.
     */
    public function test_discovery_item_points_to_a_reachable_public_shop_profile(): void
    {
        $this->shop('boutique-alpha', 'Boutique Alpha', settings: ['city' => 'Alger']);

        $this->getJson('/api/v1/public/discover?type=retail_shop')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.url', '/boutiques/boutique-alpha');

        // La cible du lien répond réellement (même périmètre opt-in).
        $this->getJson('/api/v1/public/market/sellers/boutique-alpha')
            ->assertOk()
            ->assertJsonPath('data.slug', 'boutique-alpha')
            ->assertJsonPath('data.name', 'Boutique Alpha');
    }

    /**
     * P1-02 — cohérence dépublication : une boutique retirée de la marketplace
     * disparaît de la découverte ET sa fiche cesse de répondre (404 uniforme).
     * Sans ce test, un `url` publié pourrait survivre à la dépublication.
     */
    public function test_unpublished_shop_disappears_from_discovery_and_its_profile_404s(): void
    {
        $company = $this->shop('boutique-alpha', 'Boutique Alpha');

        $this->getJson('/api/v1/public/market/sellers/boutique-alpha')->assertOk();

        RetailOnlineSettings::query()
            ->where('company_id', (string) $company->id)
            ->update(['enabled' => false]);

        $this->getJson('/api/v1/public/discover?type=retail_shop')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/public/market/sellers/boutique-alpha')->assertNotFound();
    }

    public function test_provider_returns_empty_list_when_nothing_is_eligible(): void
    {
        $this->shop('boutique-fermee', 'Boutique Fermée', enabled: false);

        /** @var iterable<PublicDiscoverableProvider> $providers */
        $providers = app()->tagged('public.discoverable');

        foreach ($providers as $provider) {
            if ($provider->type() !== 'retail_shop') {
                continue;
            }

            $this->assertSame([], $provider->search([]));

            return;
        }

        $this->fail('provider retail_shop introuvable dans le conteneur');
    }
}
