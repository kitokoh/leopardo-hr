<?php

declare(strict_types=1);

namespace Tests\Feature\Vtc;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Vtc\Domain\Models\VtcMarketplaceOperator;
use App\Shared\Contracts\Discovery\PublicDiscoverableProvider;
use App\Shared\Contracts\Discovery\PublicProfileResolver;
use App\Shared\Discovery\PublicDiscoveryService;
use App\Shared\Discovery\PublicProfileLocator;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * C-1 (#8773) — Opérateur VTC exposé sur la passerelle publique.
 *
 * Vérifie que le provider branché sur `VtcMarketplaceService` respecte les
 * invariants du contrat `PublicDiscoverableProvider` :
 *
 *  - **fail-closed** : seuls les opérateurs opt-in (`vtc_marketplace_operators`
 *    actif) dont la feature `vtc` est active et la société saine sont exposés ;
 *  - **DTO public strict** : aucun `company_id`, aucune donnée de flotte ni de
 *    grille tarifaire dans l'item sérialisé ;
 *  - **cohérence découverte / résolution** : un profil retiré de l'annuaire
 *    disparaît de la recherche ET cesse d'être joignable au contact ;
 *  - **actions réelles** : `supportedActions()` n'annonce que ce qu'un service
 *    métier sert réellement (`contact`, pas de réservation générique).
 */
class VtcPublicDiscoveryTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function operator(
        string $slug,
        string $name,
        bool $featureEnabled = true,
        bool $publish = true,
        string $operatorStatus = VtcMarketplaceOperator::STATUS_ACTIVE,
        array $attributes = [],
    ): Company {
        /** @var Company $company */
        $company = Company::factory()->create(array_merge([
            'name' => $name,
            'slug' => $slug,
            'country' => 'CM',
            'currency' => 'XAF',
        ], $attributes));

        if ($featureEnabled) {
            $company->setFeature('vtc', true);
            $company->save();
        }

        if ($publish) {
            VtcMarketplaceOperator::query()->create([
                'company_id' => $company->id,
                'status' => $operatorStatus,
            ]);
        }

        return $company;
    }

    public function test_provider_is_registered_on_the_public_gateway(): void
    {
        /** @var PublicDiscoveryService $discovery */
        $discovery = app(PublicDiscoveryService::class);

        $this->assertContains(
            'vtc_operator',
            $discovery->types(),
            'le provider VTC doit être tagué `public.discoverable`',
        );
    }

    public function test_resolver_is_registered_and_announces_only_contact(): void
    {
        /** @var PublicDiscoveryService $discovery */
        $discovery = app(PublicDiscoveryService::class);
        /** @var PublicProfileLocator $locator */
        $locator = app(PublicProfileLocator::class);

        $this->assertContains('vtc_operator', $locator->types(), 'le resolver VTC doit être tagué `public.profile_resolver`');

        $actions = $discovery->actionsByType()['vtc_operator'] ?? [];

        $this->assertSame(['contact'], $actions, 'aucune réservation/commande générique n\'est servie par la verticale VTC');
    }

    public function test_only_optin_active_operators_are_discoverable(): void
    {
        $this->operator('express-vtc', 'Express VTC');
        // Opt-in annuaire mais feature `vtc` coupée → exclu.
        $this->operator('vtc-sans-flag', 'VTC Sans Flag', featureEnabled: false);
        // Feature active mais jamais publié dans l'annuaire → exclu.
        $this->operator('vtc-non-publie', 'VTC Non Publié', publish: false);
        // Publication repassée inactive → exclu.
        $this->operator('vtc-inactif', 'VTC Inactif', operatorStatus: VtcMarketplaceOperator::STATUS_INACTIVE);
        // Société suspendue → exclue même si l'annuaire est actif.
        $this->operator('vtc-suspendu', 'VTC Suspendu', attributes: ['status' => 'suspended']);

        $response = $this->getJson('/api/v1/public/discover?type=vtc_operator')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response->assertJsonPath('data.0.type', 'vtc_operator');
        $response->assertJsonPath('data.0.slug', 'express-vtc');
        $response->assertJsonPath('data.0.name', 'Express VTC');
    }

    public function test_public_item_never_leaks_internal_data(): void
    {
        $company = $this->operator('express-vtc', 'Express VTC');

        $response = $this->getJson('/api/v1/public/discover?type=vtc_operator')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $item = $response->json('data.0');

        // Aucun identifiant interne, aucune donnée de flotte/tarification.
        $this->assertArrayNotHasKey('company_id', $item);
        $this->assertArrayNotHasKey('id', $item);
        $this->assertArrayNotHasKey('fare_profile_id', $item);
        $this->assertArrayNotHasKey('status', $item);
        $this->assertArrayNotHasKey('features', $item);

        // Le DTO reste strictement celui du contrat : 10 clés, pas plus.
        $this->assertSame([
            'type', 'slug', 'name', 'category', 'city', 'country',
            'description', 'image_url', 'latitude', 'longitude', 'url',
        ], array_keys($item));

        $this->assertStringNotContainsString((string) $company->id, (string) $response->getContent());
    }

    public function test_search_filters_by_name_and_city(): void
    {
        $this->operator('express-douala', 'Express Douala', attributes: ['city' => 'Douala']);
        $this->operator('taxi-yaounde', 'Taxi Yaoundé', attributes: ['city' => 'Yaoundé']);

        $this->getJson('/api/v1/public/discover?type=vtc_operator&q=express')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'express-douala');

        $this->getJson('/api/v1/public/discover?type=vtc_operator&city=Yaound%C3%A9')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'taxi-yaounde');
    }

    public function test_resolver_returns_owner_tenant_for_a_published_operator(): void
    {
        $company = $this->operator('express-vtc', 'Express VTC');

        /** @var PublicProfileLocator $locator */
        $locator = app(PublicProfileLocator::class);

        $resolved = $locator->locate('vtc_operator', 'express-vtc');

        $this->assertIsArray($resolved);
        $this->assertSame((string) $company->id, $resolved['company_id']);
        $this->assertSame('Express VTC', $resolved['display_name']);
    }

    public function test_resolver_is_fail_closed_when_operator_is_removed_from_the_directory(): void
    {
        $company = $this->operator('express-vtc', 'Express VTC');

        /** @var PublicProfileLocator $locator */
        $locator = app(PublicProfileLocator::class);

        $this->assertNotNull($locator->locate('vtc_operator', 'express-vtc'));

        VtcMarketplaceOperator::query()
            ->where('company_id', $company->id)
            ->update(['status' => VtcMarketplaceOperator::STATUS_INACTIVE]);

        // Dépublié : plus découvrable ET plus joignable — même règle, aucun décalage.
        $this->assertNull($locator->locate('vtc_operator', 'express-vtc'), 'un opérateur dépublié ne doit plus être résoluble');
        $this->getJson('/api/v1/public/discover?type=vtc_operator')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_contact_request_accepts_the_new_public_type(): void
    {
        $this->operator('express-vtc', 'Express VTC');

        $this->postJson('/api/v1/public/contact-requests', [
            'target_type' => 'vtc_operator',
            'target_slug' => 'express-vtc',
            'sender_name' => 'Client Test',
            'sender_email' => 'client@example.test',
            'message' => 'Bonjour, je souhaite un devis pour un trajet Douala centre.',
            'consent' => true,
            'idempotency_key' => 'idem-vtc-'.str_repeat('b', 8),
        ])->assertCreated();

        // Un slug non publié reste refusé par la résolution serveur (fail-closed).
        $this->operator('vtc-cache', 'VTC Caché', publish: false);

        $this->postJson('/api/v1/public/contact-requests', [
            'target_type' => 'vtc_operator',
            'target_slug' => 'vtc-cache',
            'sender_name' => 'Client Test',
            'sender_email' => 'client@example.test',
            'message' => 'Bonjour, message de test suffisamment long.',
            'consent' => true,
            'idempotency_key' => 'idem-vtc-'.str_repeat('c', 8),
        ])->assertStatus(404);
    }

    public function test_provider_returns_empty_list_when_no_operator_is_eligible(): void
    {
        $this->operator('vtc-non-publie', 'VTC Non Publié', publish: false);

        /** @var iterable<PublicDiscoverableProvider> $providers */
        $providers = app()->tagged('public.discoverable');

        foreach ($providers as $provider) {
            if ($provider->type() !== 'vtc_operator') {
                continue;
            }

            $this->assertSame([], $provider->search([]));

            return;
        }

        $this->fail('provider vtc_operator introuvable dans le conteneur');
    }

    public function test_resolver_contract_is_satisfied(): void
    {
        /** @var iterable<PublicProfileResolver> $resolvers */
        $resolvers = app()->tagged('public.profile_resolver');

        foreach ($resolvers as $resolver) {
            if ($resolver->type() !== 'vtc_operator') {
                continue;
            }

            $this->assertNull($resolver->resolve(''), 'un slug vide ne doit jamais résoudre');
            $this->assertNull($resolver->resolve('inconnu'), 'un slug inconnu doit retourner null');

            return;
        }

        $this->fail('resolver vtc_operator introuvable dans le conteneur');
    }
}
