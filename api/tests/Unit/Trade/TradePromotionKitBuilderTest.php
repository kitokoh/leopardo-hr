<?php

declare(strict_types=1);

namespace Tests\Unit\Trade;

use App\Modules\Trade\Domain\Models\TradeCompanyProfile;
use App\Modules\Trade\Domain\Models\TradeProduct;
use App\Modules\Trade\Infrastructure\Services\TradePromotionKitBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Kit de promotion — BC-36 TRADE, #9783.
 *
 * ## Ce que ce test protège
 *
 * Le kit est un objet de COMMUNICATION : ses défauts sont des mensonges
 * potentiels, pas des bugs d'affichage. Trois invariants sont donc verrouillés :
 *
 *  - **aucun lien mort** : `publication.url` est `null` dès que le profil n'est
 *    pas réellement joignable (dépublié, slug ou nom manquant) — jamais une URL
 *    de fiche qui répondrait 404 ;
 *  - **aucune promesse inventée** : un profil vide ne produit ni accroche ni
 *    résumé, et le brouillon n'emploie QUE les champs renseignés ;
 *  - **le kit ne publie rien** : `build()` ne modifie aucun attribut du profil.
 */
class TradePromotionKitBuilderTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function profile(array $attributes = []): TradeCompanyProfile
    {
        $profile = new TradeCompanyProfile;
        $profile->forceFill(array_merge([
            'company_id' => 'tenant-1',
            'import_enabled' => true,
            'export_enabled' => false,
            'domestic_scope' => false,
            'hq_country' => 'FR',
            'product_categories' => ['métallurgie'],
            'default_currency' => 'EUR',
            'default_incoterm' => 'CIF',
            'is_public' => true,
            'public_slug' => 'acier-nord',
            'public_name' => 'Acier Nord',
            'public_description' => 'Import-export de produits sidérurgiques.',
            'public_city' => 'Lille',
        ], $attributes));

        return $profile;
    }

    private function product(string $name, string $status = 'active'): TradeProduct
    {
        $product = new TradeProduct;
        $product->forceFill([
            'company_id' => 'tenant-1',
            'name' => $name,
            'status' => $status,
        ]);

        return $product;
    }

    private function builder(): TradePromotionKitBuilder
    {
        return new TradePromotionKitBuilder;
    }

    public function test_complete_public_profile_returns_a_working_link(): void
    {
        $kit = $this->builder()->build($this->profile());

        $this->assertTrue($kit['publication']['is_public']);
        $this->assertSame('acier-nord', $kit['publication']['slug']);
        // Segment `/entreprises`, PAS `/commerce` : `/commerce` est un préfixe
        // dashboard et redirigerait un visiteur public vers la connexion.
        $this->assertSame('/entreprises/acier-nord', $kit['publication']['url']);
    }

    public function test_unpublished_profile_never_returns_a_link(): void
    {
        $kit = $this->builder()->build(
            $this->profile(['is_public' => false]),
        );

        $this->assertFalse($kit['publication']['is_public']);
        // Un lien vers une fiche dépubliée répondrait 404 : le kit n'en donne pas.
        $this->assertNull($kit['publication']['url']);
    }

    public function test_profile_published_without_slug_returns_no_link(): void
    {
        // `is_public = true` mais slug absent : `isPubliclyVisible()` est faux,
        // donc la fiche n'est PAS servie. Le kit ne doit pas prétendre le contraire.
        $kit = $this->builder()->build(
            $this->profile(['public_slug' => null]),
        );

        $this->assertTrue($kit['publication']['is_public']);
        $this->assertNull($kit['publication']['url']);
    }

    public function test_missing_profile_returns_an_empty_but_honest_kit(): void
    {
        $kit = $this->builder()->build(null);

        $this->assertFalse($kit['publication']['is_public']);
        $this->assertNull($kit['publication']['url']);
        // Aucune donnée → aucune accroche : pas de phrase creuse.
        $this->assertNull($kit['draft']['headline']);
        $this->assertNull($kit['draft']['summary']);
        $this->assertSame([], $kit['draft']['highlights']);
        // La checklist reste présente : c'est le repère dont l'utilisateur a besoin.
        $this->assertNotEmpty($kit['checklist']);
    }

    public function test_checklist_flags_every_missing_field(): void
    {
        $kit = $this->builder()->build(null);
        $byKey = [];
        foreach ($kit['checklist'] as $entry) {
            $byKey[$entry['key']] = $entry;
        }

        foreach (['public_name', 'public_slug', 'public_description', 'public_city', 'hq_country', 'directions', 'product_categories', 'catalog'] as $key) {
            $this->assertArrayHasKey($key, $byKey);
            $this->assertFalse($byKey[$key]['done'], "Le contrôle {$key} devrait être manquant sur un profil absent.");
            $this->assertNotSame('', $byKey[$key]['hint'], "Le contrôle {$key} doit expliquer pourquoi il compte.");
        }
    }

    public function test_checklist_is_satisfied_by_a_complete_profile_with_catalog(): void
    {
        $kit = $this->builder()->build(
            $this->profile(),
            [$this->product('Tôle acier 2mm')],
        );

        foreach ($kit['checklist'] as $entry) {
            $this->assertTrue($entry['done'], "Le contrôle {$entry['key']} devrait être satisfait.");
        }
    }

    public function test_catalog_control_ignores_inactive_products(): void
    {
        $kit = $this->builder()->build(
            $this->profile(),
            [$this->product('Produit archivé', 'archived')],
        );

        $byKey = [];
        foreach ($kit['checklist'] as $entry) {
            $byKey[$entry['key']] = $entry;
        }

        // Un produit non actif n'est pas publié : il ne satisfait pas le contrôle.
        $this->assertFalse($byKey['catalog']['done']);
    }

    public function test_draft_uses_only_fields_actually_filled(): void
    {
        $kit = $this->builder()->build(
            $this->profile([
                'public_description' => null,
                'product_categories' => [],
                'default_incoterm' => null,
                'default_currency' => null,
            ]),
            [],
        );

        $summary = $kit['draft']['summary'];

        $this->assertNotNull($summary);
        // Le résumé factuel cite la ville, qui EST renseignée…
        $this->assertStringContainsString('Lille', (string) $summary);
        // …et n'invente ni catégorie, ni devise, ni Incoterm.
        $this->assertStringNotContainsString('EUR', (string) $summary);
        $this->assertStringNotContainsString('CIF', (string) $summary);
    }

    public function test_draft_cites_real_products_only(): void
    {
        $kit = $this->builder()->build(
            $this->profile(),
            [
                $this->product('Tôle acier 2mm'),
                $this->product('Poutre IPN', 'archived'),
            ],
        );

        $highlights = implode(' | ', $kit['draft']['highlights']);

        $this->assertStringContainsString('Tôle acier 2mm', $highlights);
        // Un produit archivé ne doit pas figurer dans une présentation publique.
        $this->assertStringNotContainsString('Poutre IPN', $highlights);
        $this->assertStringContainsString('1 produit', $highlights);
    }

    public function test_headline_stays_null_when_no_public_name_is_set(): void
    {
        // `public_name` est documenté comme « nom commercial publiable (≠ raison
        // sociale) ». Sans lui, le kit ne fabrique PAS d'accroche à partir de la
        // raison sociale : il laisse le champ vide et la checklist dit quoi
        // remplir. Le kit se régénère ensuite avec le bon nom.
        $kit = $this->builder()->build(
            $this->profile(['public_name' => null]),
        );

        $this->assertNull($kit['draft']['headline']);
        // Le résumé, lui, reprend la description — du texte que l'utilisateur a
        // lui-même écrit : le réutiliser n'invente rien.
        $this->assertSame('Import-export de produits sidérurgiques.', $kit['draft']['summary']);

        $byKey = [];
        foreach ($kit['checklist'] as $entry) {
            $byKey[$entry['key']] = $entry;
        }

        $this->assertFalse($byKey['public_name']['done']);
    }

    public function test_kit_never_modifies_the_profile(): void
    {
        $profile = $this->profile();
        $before = $profile->getAttributes();

        $kit = $this->builder()->build($profile);

        // Un kit de LECTURE qui écrit serait un piège : la publication reste
        // l'acte séparé `PUT /trade/profile/publication`.
        $this->assertSame($before, $profile->getAttributes());
        $this->assertArrayHasKey('publication', $kit);
    }

    public function test_kit_states_it_publishes_nothing(): void
    {
        $kit = $this->builder()->build($this->profile());

        $this->assertStringContainsString('ne publie rien', $kit['disclaimer']);
    }
}
