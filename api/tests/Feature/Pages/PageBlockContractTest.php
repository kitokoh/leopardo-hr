<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Modules\Pages\Domain\Blocks\CorePageBlocks;
use App\Modules\Pages\Domain\Blocks\PageBlock;
use App\Modules\Pages\Domain\Blocks\PageBlockCollection;
use App\Modules\Pages\Domain\Blocks\PageBlockProvider;
use App\Modules\Pages\Domain\Blocks\PageBlockRegistry;
use App\Modules\Pages\Domain\Blocks\PageBlockSchemaValidator;
use App\Modules\Pages\Domain\Blocks\PageBlockTypeDefinition;
use App\Modules\Pages\Domain\Exceptions\DuplicatePageBlockTypeException;
use Tests\TestCase;

/**
 * #8724 (BC PAGES) — contrat de blocs extensible fourni par les BC verticaux.
 *
 * Couvre les critères d'acceptation de l'issue, un par un :
 *  - identifiant qualifié + BC propriétaire + `schema_version` ;
 *  - schéma strict : propriétés, tailles, cardinalités, protocoles d'URL ;
 *  - type inconnu / version non supportée → jamais de HTML arbitraire ;
 *  - types Showcase compatibles migrés sans perte (nom des champs identique) ;
 *  - tests de validation, versionnement, ordre et renderer absent.
 *
 * Aucun de ces tests ne touche la base : le registre et le validateur sont de
 * purs objets, c'est précisément ce qui les rend rapides et sûrs à faire
 * évoluer (et ce que l'issue appelle « contrat »).
 */
class PageBlockContractTest extends TestCase
{
    private PageBlockSchemaValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PageBlockSchemaValidator;
    }

    // ── Registre : identifiants qualifiés ───────────────────────────────────

    public function test_core_registry_exposes_the_seven_showcase_compatible_types(): void
    {
        $registry = PageBlockRegistry::build([]);

        $this->assertCount(7, $registry->all());
        foreach (['core.hero', 'core.features', 'core.gallery', 'core.testimonials', 'core.products', 'core.contact', 'core.footer'] as $id) {
            $this->assertTrue($registry->has($id), sprintf('Le type « %s » doit être fourni par le socle.', $id));
        }

        // Tous rendables : un type core sans renderer serait un bug du socle.
        $this->assertSame([], $registry->unrenderableIds());
    }

    public function test_unqualified_type_identifier_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PageBlockTypeDefinition(
            id: 'hero', // non qualifié
            ownerBc: 'core',
            schemaVersion: 1,
            schema: ['type' => 'object'],
        );
    }

    public function test_provider_cannot_escape_its_own_namespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // Un fournisseur `restaurant` qui déclare un type `core.hero` :
        // collision garantie avec le socle, et un contenu restaurant rendu par
        // le renderer du hero.
        new PageBlockTypeDefinition(
            id: 'core.hero',
            ownerBc: 'restaurant',
            schemaVersion: 1,
            schema: ['type' => 'object'],
        );
    }

    public function test_schema_version_must_be_positive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PageBlockTypeDefinition(
            id: 'restaurant.menu',
            ownerBc: 'restaurant',
            schemaVersion: 0,
            schema: ['type' => 'object'],
        );
    }

    public function test_schema_root_must_be_an_object(): void
    {
        // Un schéma racine `string` accepterait n'importe quelle chaîne — y
        // compris du HTML — comme contenu de bloc.
        $this->expectException(\InvalidArgumentException::class);

        new PageBlockTypeDefinition(
            id: 'restaurant.menu',
            ownerBc: 'restaurant',
            schemaVersion: 1,
            schema: ['type' => 'string'],
        );
    }

    public function test_duplicate_type_registration_fails_loudly_instead_of_silently_overwriting(): void
    {
        $registry = PageBlockRegistry::build([$this->restaurantProvider()]);

        $this->expectException(DuplicatePageBlockTypeException::class);

        // Deuxième fournisseur qui prétend le même type : le dernier ne doit
        // pas gagner en silence.
        $registry->register(new PageBlockTypeDefinition(
            id: 'restaurant.menu',
            ownerBc: 'restaurant',
            schemaVersion: 1,
            schema: ['type' => 'object'],
        ));
    }

    public function test_registering_the_same_type_from_two_providers_throws_at_build_time(): void
    {
        $this->expectException(DuplicatePageBlockTypeException::class);

        PageBlockRegistry::build([$this->restaurantProvider(), $this->restaurantProvider()]);
    }

    public function test_registry_is_partitioned_by_owner_bc(): void
    {
        $registry = PageBlockRegistry::build([$this->restaurantProvider()]);

        $this->assertArrayHasKey('restaurant.menu', $registry->byOwner('restaurant'));
        $this->assertArrayNotHasKey('restaurant.menu', $registry->byOwner('core'));
        $this->assertCount(7, $registry->byOwner('core'));
    }

    // ── Validation ──────────────────────────────────────────────────────────

    public function test_valid_core_hero_passes(): void
    {
        $registry = PageBlockRegistry::build([]);
        $hero = $registry->get('core.hero');
        $this->assertNotNull($hero);

        $this->assertSame([], $this->validator->validate($hero, [
            'heading' => 'Chez Léa',
            'subheading' => 'Cuisine sénégalaise',
            'cta_url' => '/p/chez-lea/reserver',
        ]));
    }

    public function test_required_field_is_enforced(): void
    {
        $registry = PageBlockRegistry::build([]);
        $hero = $registry->get('core.hero');
        $this->assertNotNull($hero);

        $errors = $this->validator->validate($hero, ['subheading' => 'sans titre']);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('content.heading', implode(' ', $errors));
    }

    public function test_unknown_property_is_rejected_by_default(): void
    {
        $registry = PageBlockRegistry::build([]);
        $hero = $registry->get('core.hero');
        $this->assertNotNull($hero);

        // `additionalProperties` absent du schéma ⇒ refus (défaut strict),
        // contrairement au défaut permissif de JSON Schema.
        $errors = $this->validator->validate($hero, [
            'heading' => 'Titre',
            'html' => '<script>alert(1)</script>',
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('content.html', implode(' ', $errors));
    }

    public function test_string_length_is_bounded(): void
    {
        $registry = PageBlockRegistry::build([]);
        $hero = $registry->get('core.hero');
        $this->assertNotNull($hero);

        $errors = $this->validator->validate($hero, ['heading' => str_repeat('a', 121)]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('longueur maximale', implode(' ', $errors));
    }

    public function test_array_cardinality_is_bounded_by_the_schema(): void
    {
        $registry = PageBlockRegistry::build([]);
        $features = $registry->get('core.features');
        $this->assertNotNull($features);

        $items = array_fill(0, 13, ['title' => 'atout']); // maxItems = 12
        $errors = $this->validator->validate($features, ['items' => $items]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('au plus 12', implode(' ', $errors));
    }

    public function test_javascript_url_is_rejected(): void
    {
        $registry = PageBlockRegistry::build([]);
        $hero = $registry->get('core.hero');
        $this->assertNotNull($hero);

        // Le cœur du critère de sécurité : un CTA ne peut pas porter un
        // protocole exécutable, même si le champ respecte sa longueur.
        $errors = $this->validator->validate($hero, [
            'heading' => 'Titre',
            'cta_url' => 'javascript:alert(document.cookie)',
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('protocole', implode(' ', $errors));
    }

    public function test_data_url_is_rejected(): void
    {
        $registry = PageBlockRegistry::build([]);
        $gallery = $registry->get('core.gallery');
        $this->assertNotNull($gallery);

        $errors = $this->validator->validate($gallery, [
            'items' => [['image_url' => 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=']],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('protocole', implode(' ', $errors));
    }

    public function test_https_and_relative_urls_are_accepted(): void
    {
        $registry = PageBlockRegistry::build([]);
        $gallery = $registry->get('core.gallery');
        $this->assertNotNull($gallery);

        $this->assertSame([], $this->validator->validate($gallery, [
            'items' => [
                ['image_url' => 'https://cdn.leopardo.test/a.jpg'],
                ['image_url' => '/media/local.jpg'],
            ],
        ]));
    }

    public function test_contact_block_accepts_mailto_and_tel_but_not_javascript(): void
    {
        $registry = PageBlockRegistry::build([]);
        $contact = $registry->get('core.contact');
        $this->assertNotNull($contact);

        $this->assertSame([], $this->validator->validate($contact, [
            'email' => 'contact@chez-lea.sn',
            'links' => [
                ['label' => 'Écrire', 'url' => 'mailto:contact@chez-lea.sn'],
                ['label' => 'Appeler', 'url' => 'tel:+221338000000'],
            ],
        ]));

        $errors = $this->validator->validate($contact, [
            'links' => [['label' => 'Piège', 'url' => 'javascript:void(0)']],
        ]);
        $this->assertNotEmpty($errors);
    }

    public function test_max_array_items_ceiling_applies_even_if_schema_is_permissive(): void
    {
        // Un fournisseur qui déclare `maxItems: 10000` ne doit pas pouvoir
        // faire ingérer 10 000 entrées : le plafond dur du validateur prime.
        $permissive = new PageBlockTypeDefinition(
            id: 'restaurant.huge',
            ownerBc: 'restaurant',
            schemaVersion: 1,
            schema: [
                'type' => 'object',
                'properties' => [
                    'items' => ['type' => 'array', 'maxItems' => 10_000, 'items' => ['type' => 'string']],
                ],
                'required' => [],
            ],
        );

        $errors = $this->validator->validate($permissive, [
            'items' => array_fill(0, PageBlockSchemaValidator::MAX_ARRAY_ITEMS + 1, 'x'),
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('au plus 100', implode(' ', $errors));
    }

    public function test_depth_ceiling_is_enforced(): void
    {
        $deep = ['type' => 'object', 'properties' => [], 'required' => []];
        $node = $deep;
        for ($i = 0; $i < PageBlockSchemaValidator::MAX_DEPTH + 2; $i++) {
            $child = ['type' => 'object', 'properties' => ['n' => $node], 'required' => []];
            $node = $child;
        }

        $definition = new PageBlockTypeDefinition(
            id: 'restaurant.deep',
            ownerBc: 'restaurant',
            schemaVersion: 1,
            schema: $node,
        );

        $payload = 'leaf';
        for ($i = 0; $i < PageBlockSchemaValidator::MAX_DEPTH + 2; $i++) {
            $payload = ['n' => $payload];
        }

        $errors = $this->validator->validate($definition, is_array($payload) ? $payload : []);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('profondeur maximale', implode(' ', $errors));
    }

    // ── Versionnement ───────────────────────────────────────────────────────

    public function test_block_with_unsupported_schema_version_is_reported(): void
    {
        $registry = PageBlockRegistry::build([$this->restaurantProvider()]);
        $collection = new PageBlockCollection([
            new PageBlock(
                id: 'b1',
                type: 'restaurant.menu',
                schemaVersion: 2, // contrat en v1
                position: 0,
                content: ['sections' => [['title' => 'Entrées']]],
            ),
        ]);

        $errors = $collection->validate($registry, $this->validator);

        $this->assertArrayHasKey(0, $errors);
        $this->assertStringContainsString('Version de schéma non supportée', implode(' ', $errors[0]));
    }

    public function test_block_with_unknown_type_is_reported(): void
    {
        $registry = PageBlockRegistry::build([]);
        $collection = new PageBlockCollection([
            new PageBlock(id: 'b1', type: 'travel.itinerary', schemaVersion: 1, position: 0, content: []),
        ]);

        $errors = $collection->validate($registry, $this->validator);

        $this->assertArrayHasKey(0, $errors);
        $this->assertStringContainsString('Type de bloc inconnu', implode(' ', $errors[0]));
    }

    public function test_all_block_errors_are_reported_at_once(): void
    {
        // L'éditeur doit pouvoir tout afficher : une erreur à la fois sur huit
        // blocs est inutilisable.
        $registry = PageBlockRegistry::build([]);
        $collection = new PageBlockCollection([
            new PageBlock(id: 'b1', type: 'core.hero', schemaVersion: 1, position: 0, content: []),
            new PageBlock(id: 'b2', type: 'core.features', schemaVersion: 1, position: 1, content: []),
        ]);

        $errors = $collection->validate($registry, $this->validator);

        $this->assertArrayHasKey(0, $errors);
        $this->assertArrayHasKey(1, $errors);
    }

    public function test_locales_are_validated_too(): void
    {
        $registry = PageBlockRegistry::build([]);
        $collection = new PageBlockCollection([
            new PageBlock(
                id: 'b1',
                type: 'core.hero',
                schemaVersion: 1,
                position: 0,
                content: ['heading' => 'Bienvenue'],
                locales: ['ar' => ['heading' => 'مرحبا', 'unknown' => 'x']],
            ),
        ]);

        $errors = $collection->validate($registry, $this->validator);

        $this->assertArrayHasKey(0, $errors);
        $this->assertStringContainsString('[ar]', implode(' ', $errors[0]));
    }

    // ── Ordre ───────────────────────────────────────────────────────────────

    public function test_positions_are_normalized_and_gaps_closed(): void
    {
        $collection = new PageBlockCollection([
            new PageBlock('b2', 'core.features', 1, 7, ['items' => [['title' => 'a']]]),
            new PageBlock('b1', 'core.hero', 1, 3, ['heading' => 'Titre']),
        ]);

        $normalized = $collection->normalized();

        $this->assertSame(['b1', 'b2'], array_map(static fn (PageBlock $b): string => $b->id, $normalized->blocks));
        $this->assertSame([0, 1], array_map(static fn (PageBlock $b): int => $b->position, $normalized->blocks));
    }

    public function test_equal_positions_keep_the_original_order_deterministically(): void
    {
        $collection = new PageBlockCollection([
            new PageBlock('a', 'core.hero', 1, 0, ['heading' => 'A']),
            new PageBlock('b', 'core.footer', 1, 0, ['text' => 'B']),
            new PageBlock('c', 'core.contact', 1, 0, []),
        ]);

        $this->assertSame(
            ['a', 'b', 'c'],
            array_map(static fn (PageBlock $b): string => $b->id, $collection->normalized()->blocks),
        );
    }

    // ── Rendu : jamais de HTML arbitraire ───────────────────────────────────

    public function test_unrenderable_blocks_are_omitted_with_a_reason_not_executed(): void
    {
        $registry = PageBlockRegistry::build([
            new class implements PageBlockProvider
            {
                public function ownerBc(): string
                {
                    return 'restaurant';
                }

                public function blockTypes(): array
                {
                    return [
                        'restaurant.menu' => new PageBlockTypeDefinition(
                            id: 'restaurant.menu',
                            ownerBc: 'restaurant',
                            schemaVersion: 1,
                            renderer: null, // contrat livré, rendu pas encore
                            schema: ['type' => 'object', 'properties' => [], 'required' => []],
                        ),
                    ];
                }
            },
        ]);

        $collection = new PageBlockCollection([
            new PageBlock('b1', 'core.hero', 1, 0, ['heading' => 'Titre']),
            new PageBlock('b2', 'restaurant.menu', 1, 1, ['sections' => []]),
            new PageBlock('b3', 'travel.itinerary', 1, 2, ['steps' => []]),
        ]);

        $result = $collection->renderableBlocks($registry);

        $this->assertSame(['b1'], array_map(static fn (PageBlock $b): string => $b->id, $result['blocks']));
        $this->assertSame(
            [
                ['type' => 'restaurant.menu', 'reason' => 'renderer_missing'],
                ['type' => 'travel.itinerary', 'reason' => 'unknown_type'],
            ],
            $result['omitted'],
        );
    }

    public function test_unsupported_version_is_omitted_at_render_time(): void
    {
        $registry = PageBlockRegistry::build([]);
        $collection = new PageBlockCollection([
            new PageBlock('b1', 'core.hero', 9, 0, ['heading' => 'Titre']),
        ]);

        $result = $collection->renderableBlocks($registry);

        $this->assertSame([], $result['blocks']);
        $this->assertSame(
            [['type' => 'core.hero', 'reason' => 'unsupported_schema_version']],
            $result['omitted'],
        );
    }

    // ── Compatibilité Showcase ──────────────────────────────────────────────

    public function test_showcase_types_map_to_core_types_without_loss(): void
    {
        $map = CorePageBlocks::showcaseEquivalent();

        $this->assertSame(
            [
                'hero' => 'core.hero',
                'features' => 'core.features',
                'gallery' => 'core.gallery',
                'testimonials' => 'core.testimonials',
                'products' => 'core.products',
                'contact' => 'core.contact',
                'footer' => 'core.footer',
            ],
            $map,
        );
    }

    public function test_every_showcase_section_type_has_a_pages_equivalent(): void
    {
        // Verrou anti-dérive : si Showcase ajoute un type, ce test échoue et
        // force à décider explicitement de son équivalent Pages (au lieu de le
        // perdre silencieusement à la migration #8727).
        $showcaseTypes = array_map(
            static fn (\App\Modules\Showcase\Domain\Enums\ShowcaseSectionType $t): string => $t->value,
            \App\Modules\Showcase\Domain\Enums\ShowcaseSectionType::cases(),
        );

        $mapped = array_keys(CorePageBlocks::showcaseEquivalent());
        sort($showcaseTypes);
        sort($mapped);

        $this->assertSame($showcaseTypes, $mapped);
    }

    public function test_showcase_content_migrates_without_field_loss(): void
    {
        // Le contenu d'une section Showcase doit valider tel quel contre le
        // schéma Pages correspondant : c'est la définition de « sans perte ».
        $registry = PageBlockRegistry::build([]);
        $validator = new PageBlockSchemaValidator;

        $hero = $registry->get('core.hero');
        $this->assertNotNull($hero);
        $this->assertSame([], $validator->validate($hero, [
            'heading' => 'Bienvenue chez Léa',
            'subheading' => 'Depuis 2019',
            'cta_label' => 'Nous trouver',
            'cta_url' => 'https://chez-lea.sn',
        ]));

        $footer = $registry->get('core.footer');
        $this->assertNotNull($footer);
        $this->assertSame([], $validator->validate($footer, [
            'text' => '© 2026 Chez Léa',
        ]));
    }

    public function test_products_block_stores_references_not_prices(): void
    {
        $registry = PageBlockRegistry::build([]);
        $products = $registry->get('core.products');
        $this->assertNotNull($products);

        // Références : valide.
        $this->assertSame([], $this->validator->validate($products, [
            'provider' => 'catalog',
            'product_refs' => ['prod_123', 'prod_456'],
        ]));

        // Prix dupliqué : refusé. Un prix figé dans une Page publique finit
        // toujours par diverger du prix réellement facturé.
        $errors = $this->validator->validate($products, [
            'product_refs' => ['prod_123'],
            'price' => 1500,
        ]);
        $this->assertNotEmpty($errors);
    }

    // ── Conteneur ───────────────────────────────────────────────────────────

    public function test_service_container_resolves_the_registry_as_a_singleton(): void
    {
        $a = $this->app->make(PageBlockRegistry::class);
        $b = $this->app->make(PageBlockRegistry::class);

        $this->assertSame($a, $b, 'Le registre doit être figé pour la requête (singleton).');
        $this->assertTrue($a->has('core.hero'));
    }

    // ── Helper ──────────────────────────────────────────────────────────────

    private function restaurantProvider(): PageBlockProvider
    {
        return new class implements PageBlockProvider
        {
            public function ownerBc(): string
            {
                return 'restaurant';
            }

            public function blockTypes(): array
            {
                return [
                    'restaurant.menu' => new PageBlockTypeDefinition(
                        id: 'restaurant.menu',
                        ownerBc: 'restaurant',
                        schemaVersion: 1,
                        renderer: 'pages.blocks.restaurant_menu',
                        schema: [
                            'type' => 'object',
                            'properties' => [
                                'sections' => [
                                    'type' => 'array',
                                    'minItems' => 1,
                                    'maxItems' => 10,
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'title' => ['type' => 'string', 'maxLength' => 80],
                                        ],
                                        'required' => ['title'],
                                    ],
                                ],
                            ],
                            'required' => ['sections'],
                        ],
                    ),
                ];
            }
        };
    }
}
