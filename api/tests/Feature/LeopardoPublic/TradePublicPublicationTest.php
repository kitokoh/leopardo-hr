<?php

declare(strict_types=1);

namespace Tests\Feature\LeopardoPublic;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Trade\Domain\Models\TradeCompanyProfile;
use App\Modules\Trade\Infrastructure\Services\TradeDiscoverableProvider;
use App\Modules\Trade\Infrastructure\Services\TradeProfileResolver;
use Tests\TestCase;

/**
 * P2-01 (#8802) — publication publique Trade, sémantique fail-closed.
 *
 * Trade est la première verticale dotée d'un opt-in public **a posteriori** :
 * elle n'avait aucune colonne `is_public`, aucun `public_slug`, aucune surface
 * publique. Ces tests verrouillent les quatre invariants de la sémantique
 * retenue, dans l'ordre où ils peuvent casser :
 *
 *  1. **défaut privé** — un profil qui n'a jamais opté-in n'est pas publié ;
 *  2. **fail-closed partiel** — `is_public = true` mais slug ou nom manquant
 *     ne suffit pas (pas de repli sur la raison sociale) ;
 *  3. **DTO strict** — aucun champ interne ne sort (`notes`, `company_id`,
 *     `capabilities`) ;
 *  4. **404 uniforme** — un slug inconnu et un profil dépublié sont
 *     indistinguables pour l'appelant.
 */
class TradePublicPublicationTest extends TestCase
{
    use \Tests\RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create([
            'features' => ['trade' => true],
            'status' => 'active',
            // Pays EXPLICITE : la factory le tire au hasard (`$faker->randomElement`).
            // Les assertions sur `country` ne doivent pas d\u00e9pendre de ce tirage,
            // sinon le test passe ou \u00e9choue selon la graine.
            'country' => 'FR',
        ]);

        $this->company = $company;
    }

    private function provider(): TradeDiscoverableProvider
    {
        return $this->app->make(TradeDiscoverableProvider::class);
    }

    private function resolver(): TradeProfileResolver
    {
        return $this->app->make(TradeProfileResolver::class);
    }

    /**
     * Fabrique un profil Trade dans le tenant courant.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeProfile(array $overrides = [], ?Company $company = null): TradeCompanyProfile
    {
        $target = $company ?? $this->company;

        // `BelongsToCompany` renseigne `company_id` depuis le TenantManager :
        // hors contexte tenant, la création lève NOT NULL. On passe donc par
        // `withinTenant`, comme tout le reste du monorepo.
        return app(TenantManager::class)->withinTenant(
            $target,
            static fn (): TradeCompanyProfile => TradeCompanyProfile::query()->create(array_merge([
                'import_enabled' => true,
                'export_enabled' => false,
                'domestic_scope' => false,
                'hq_country' => 'FR',
                'default_currency' => 'EUR',
                'notes' => 'MARGE INTERNE — NE JAMAIS PUBLIER',
            ], $overrides))
        );
    }

    public function test_a_profile_that_never_opted_in_is_not_published(): void
    {
        // Profil complet mais SANS opt-in : c'est le cas par défaut en base.
        $this->makeProfile([
            'public_slug' => 'import-export-non-optin',
            'public_name' => 'Non Opt-in SA',
        ]);

        $this->assertFalse(
            (bool) TradeCompanyProfile::query()->first()?->is_public,
            '`is_public` doit valoir FALSE par défaut : la migration ne doit jamais publier sans consentement.'
        );

        $items = $this->provider()->search([]);

        $this->assertSame([], $items, 'Un profil non opt-in ne doit jamais apparaître dans la découverte.');
    }

    public function test_a_profile_with_partial_public_data_stays_private(): void
    {
        // Opt-in posé, mais nom public manquant : rien à afficher, donc rien à
        // publier. Pas de repli sur `companies.name` — ce serait publier une
        // raison sociale que le professionnel n'a pas choisie.
        $this->makeProfile([
            'is_public' => true,
            'public_slug' => 'sans-nom-public',
            'public_name' => null,
        ]);

        $this->assertSame([], $this->provider()->search([]));
    }

    public function test_an_opted_in_profile_is_published_with_a_strict_dto(): void
    {
        $this->makeProfile([
            'is_public' => true,
            'public_slug' => 'atlas-import-export',
            'public_name' => 'Atlas Import Export',
            'public_description' => 'Importateur de matériel industriel.',
            'public_city' => 'Lyon',
            'product_categories' => ['machines', 'composants'],
        ]);

        $items = $this->provider()->search([]);

        $this->assertCount(1, $items);

        $item = $items[0];

        $this->assertSame('trade_company', $item['type']);
        $this->assertSame('atlas-import-export', $item['slug']);
        $this->assertSame('Atlas Import Export', $item['name']);
        $this->assertSame('Lyon', $item['city']);
        $this->assertSame('FR', $item['country']);
        $this->assertSame('/entreprises/atlas-import-export', $item['url']);

        // DTO STRICT : les internes ne doivent pas exister, pas seulement être
        // vides. `assertArrayNotHasKey` attrape aussi une fuite par ajout.
        $this->assertArrayNotHasKey('company_id', $item);
        $this->assertArrayNotHasKey('notes', $item);
        $this->assertArrayNotHasKey('capabilities', $item);

        // Aucune valeur de la fiche ne doit contenir la note interne.
        $this->assertStringNotContainsString('MARGE INTERNE', (string) json_encode($item));
    }

    public function test_search_filters_on_the_published_name_and_city(): void
    {
        // ⚠️ `trade_company_profiles_company_unique` impose UN profil par
        // société : deux profils Trade dans le MÊME tenant sont impossibles par
        // construction. Un premier jet de ce test en créait deux et échouait sur
        // la contrainte — la faute était au test, pas au schéma. Le second
        // professionnel vit donc dans un tenant distinct, ce qui correspond de
        // toute façon au cas réel : deux entreprises = deux comptes.
        $this->makeProfile([
            'is_public' => true,
            'public_slug' => 'atlas-import',
            'public_name' => 'Atlas Import',
            'public_city' => 'Lyon',
        ]);

        /** @var Company $other */
        $other = Company::factory()->create([
            'features' => ['trade' => true],
            'status' => 'active',
            'country' => 'TR',
        ]);

        $this->makeProfile([
            'is_public' => true,
            'public_slug' => 'bosphore-trade',
            'public_name' => 'Bosphore Trade',
            'public_city' => 'Istanbul',
        ], $other);

        $byName = $this->provider()->search(['q' => 'atlas']);
        $this->assertCount(1, $byName);
        $this->assertSame('atlas-import', $byName[0]['slug']);

        $byCity = $this->provider()->search(['city' => 'istanbul']);
        $this->assertCount(1, $byCity);
        $this->assertSame('bosphore-trade', $byCity[0]['slug']);

        // Filtre sur une valeur absente : aucun repli sur « tout ».
        $this->assertSame([], $this->provider()->search(['q' => 'inexistant']));
    }

    public function test_the_resolver_returns_the_owning_tenant_for_a_published_slug(): void
    {
        $profile = $this->makeProfile([
            'is_public' => true,
            'public_slug' => 'atlas-import',
            'public_name' => 'Atlas Import',
        ]);

        $resolved = $this->resolver()->resolve('atlas-import');

        $this->assertIsArray($resolved);
        $this->assertSame((string) $profile->company_id, $resolved['company_id']);
        $this->assertSame('Atlas Import', $resolved['display_name']);
    }

    public function test_the_resolver_is_404_uniform_for_unknown_and_unpublished_slugs(): void
    {
        $this->makeProfile([
            'is_public' => false,
            'public_slug' => 'depubliee',
            'public_name' => 'Dépubliée SA',
        ]);

        // Indistinguables : les deux renvoient null, donc le contrôleur rend le
        // MEME 404. Un visiteur ne peut pas énumérer les professionnels.
        $this->assertNull($this->resolver()->resolve('depubliee'));
        $this->assertNull($this->resolver()->resolve('slug-qui-nexiste-pas'));
        $this->assertNull($this->resolver()->resolve(''));
    }

    public function test_the_resolver_rejects_a_published_profile_when_the_tenant_is_suspended(): void
    {
        $profile = $this->makeProfile([
            'is_public' => true,
            'public_slug' => 'suspendue',
            'public_name' => 'Suspendue SA',
        ]);

        // Le profil reste techniquement opt-in ; c'est la SOCIÉTÉ qui tombe.
        // La résolution doit suivre l'état commercial, sinon une entreprise
        // radiée resterait joignable indéfiniment.
        Company::query()->whereKey($profile->company_id)->update(['status' => 'suspended']);

        $this->assertNull($this->resolver()->resolve('suspendue'));
    }
}
