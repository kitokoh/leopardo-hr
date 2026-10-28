<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Blocks;

/**
 * Blocs du socle `core.*` (BC PAGES, #8724).
 *
 * Ce sont les sept types de sections Showcase (BC-27) portés dans l'espace de
 * noms neutre de Pages, avec le **même contenu à la clé près** : c'est ce qui
 * rend la migration #8727 sans perte. La règle de l'issue est explicite —
 * « les types Showcase compatibles sont migrés sans perte ». Toute divergence
 * de nom de champ casserait cette propriété, donc les noms de propriétés sont
 * identiques à `ShowcaseSectionSchemaRegistry` (`heading`, `subheading`,
 * `image_url`, `cta_label`, `cta_url`, `items`, …).
 *
 * ## Ce qui change par rapport au registre Showcase
 *
 * 1. `format` sur les URL (`image_url`, `cta_url`) : Showcase ne validait que
 *    la longueur, ce qui acceptait `javascript:` dans un bouton. Ici le
 *    protocole est vérifié par le validateur.
 * 2. `additionalProperties: false` **explicite** partout : dans Showcase c'était
 *    déjà le cas sur les objets racine, on l'étend aux objets imbriqués.
 * 3. Chaque type déclare un `renderer` (nom du composant de rendu public) :
 *    un type sans renderer est stocké mais omis du rendu, avec télémétrie.
 *
 * `products` référence le catalogue via un contrat (`catalog.product_ref`), il
 * ne stocke pas de prix : le prix affiché doit venir du BC Catalog au moment du
 * rendu, sinon une Page afficherait des prix périmés — c'est la généralisation
 * du pattern `PublishedProductsProvider` demandée dans l'issue.
 */
final class CorePageBlocks
{
    /**
     * @return list<PageBlockTypeDefinition>
     */
    public static function types(): array
    {
        return [
            self::hero(),
            self::features(),
            self::gallery(),
            self::testimonials(),
            self::products(),
            self::contact(),
            self::footer(),
        ];
    }

    public static function hero(): PageBlockTypeDefinition
    {
        return new PageBlockTypeDefinition(
            id: 'core.hero',
            ownerBc: 'core',
            schemaVersion: 1,
            renderer: 'pages.blocks.hero',
            isCore: true,
            label: 'Hero',
            schema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'heading' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
                    'subheading' => ['type' => 'string', 'maxLength' => 280],
                    'image_url' => ['type' => 'string', 'maxLength' => 500, 'format' => 'uri'],
                    'cta_label' => ['type' => 'string', 'maxLength' => 40],
                    'cta_url' => ['type' => 'string', 'maxLength' => 500, 'format' => 'uri'],
                ],
                'required' => ['heading'],
            ],
        );
    }

    public static function features(): PageBlockTypeDefinition
    {
        return new PageBlockTypeDefinition(
            id: 'core.features',
            ownerBc: 'core',
            schemaVersion: 1,
            renderer: 'pages.blocks.features',
            isCore: true,
            label: 'Atouts',
            schema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'title' => ['type' => 'string', 'maxLength' => 120],
                    'items' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 12,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'icon' => ['type' => 'string', 'maxLength' => 40],
                                'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
                                'description' => ['type' => 'string', 'maxLength' => 500],
                            ],
                            'required' => ['title'],
                        ],
                    ],
                ],
                'required' => ['items'],
            ],
        );
    }

    public static function gallery(): PageBlockTypeDefinition
    {
        return new PageBlockTypeDefinition(
            id: 'core.gallery',
            ownerBc: 'core',
            schemaVersion: 1,
            renderer: 'pages.blocks.gallery',
            isCore: true,
            label: 'Galerie',
            schema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'title' => ['type' => 'string', 'maxLength' => 120],
                    'items' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 20,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'image_url' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500, 'format' => 'uri'],
                                'caption' => ['type' => 'string', 'maxLength' => 200],
                            ],
                            'required' => ['image_url'],
                        ],
                    ],
                ],
                'required' => ['items'],
            ],
        );
    }

    public static function testimonials(): PageBlockTypeDefinition
    {
        return new PageBlockTypeDefinition(
            id: 'core.testimonials',
            ownerBc: 'core',
            schemaVersion: 1,
            renderer: 'pages.blocks.testimonials',
            isCore: true,
            label: 'Témoignages',
            schema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'title' => ['type' => 'string', 'maxLength' => 120],
                    'items' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 12,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'quote' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500],
                                'author' => ['type' => 'string', 'maxLength' => 120],
                                'role' => ['type' => 'string', 'maxLength' => 120],
                                'avatar_url' => ['type' => 'string', 'maxLength' => 500, 'format' => 'uri'],
                            ],
                            'required' => ['quote'],
                        ],
                    ],
                ],
                'required' => ['items'],
            ],
        );
    }

    /**
     * Bloc catalogue : ne stocke QUE des références de produits.
     *
     * Aucun prix, aucun nom dupliqué : le contenu est résolu au rendu par le
     * BC Catalog (`catalog.product_ref`). Dupliquer le prix ici est le piège
     * qui fait afficher un tarif périmé sur une page publique — et une page
     * publique au mauvais prix est un problème juridique, pas cosmétique.
     */
    public static function products(): PageBlockTypeDefinition
    {
        return new PageBlockTypeDefinition(
            id: 'core.products',
            ownerBc: 'core',
            schemaVersion: 1,
            renderer: 'pages.blocks.products',
            isCore: true,
            label: 'Produits',
            schema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'title' => ['type' => 'string', 'maxLength' => 120],
                    'provider' => ['type' => 'string', 'maxLength' => 60],
                    'product_refs' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 24,
                        'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 80],
                    ],
                    'show_prices' => ['type' => 'boolean'],
                ],
                'required' => ['product_refs'],
            ],
        );
    }

    public static function contact(): PageBlockTypeDefinition
    {
        return new PageBlockTypeDefinition(
            id: 'core.contact',
            ownerBc: 'core',
            schemaVersion: 1,
            renderer: 'pages.blocks.contact',
            isCore: true,
            label: 'Contact',
            schema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'title' => ['type' => 'string', 'maxLength' => 120],
                    'phone' => ['type' => 'string', 'maxLength' => 40],
                    'email' => ['type' => 'string', 'maxLength' => 190],
                    'address' => ['type' => 'string', 'maxLength' => 300],
                    'map_url' => ['type' => 'string', 'maxLength' => 500, 'format' => 'uri'],
                    'links' => [
                        'type' => 'array',
                        'maxItems' => 8,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'label' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 60],
                                'url' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500, 'format' => 'contact-uri'],
                            ],
                            'required' => ['label', 'url'],
                        ],
                    ],
                ],
                'required' => [],
            ],
        );
    }

    public static function footer(): PageBlockTypeDefinition
    {
        return new PageBlockTypeDefinition(
            id: 'core.footer',
            ownerBc: 'core',
            schemaVersion: 1,
            renderer: 'pages.blocks.footer',
            isCore: true,
            label: 'Pied de page',
            schema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'text' => ['type' => 'string', 'maxLength' => 300],
                    'legal_links' => [
                        'type' => 'array',
                        'maxItems' => 6,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'label' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 60],
                                'url' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500, 'format' => 'contact-uri'],
                            ],
                            'required' => ['label', 'url'],
                        ],
                    ],
                ],
                'required' => [],
            ],
        );
    }

    /**
     * Correspondance type Showcase → type Pages, pour la migration #8727.
     *
     * Table explicite et figée plutôt qu'une transformation algorithmique : le
     * jour où un type Showcase n'a pas d'équivalent, on veut le voir dans ce
     * fichier, pas le découvrir dans un log de migration.
     *
     * @return array<string, string>
     */
    public static function showcaseEquivalent(): array
    {
        return [
            'hero' => 'core.hero',
            'features' => 'core.features',
            'gallery' => 'core.gallery',
            'testimonials' => 'core.testimonials',
            'products' => 'core.products',
            'contact' => 'core.contact',
            'footer' => 'core.footer',
        ];
    }
}
