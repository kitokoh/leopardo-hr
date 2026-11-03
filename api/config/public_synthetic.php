<?php

declare(strict_types=1);

/**
 * COULOIR 16 — M0 · Jeu de données synthétiques de la surface publique.
 *
 * ## Pourquoi un fichier de configuration et pas des constantes dans la commande
 *
 * Le cahier des charges est explicite : « aucune ville, aucun pays, aucune
 * catégorie codés en dur ». Une commande qui contiendrait `'Alger'` en dur
 * violerait la règle au même titre qu'un contrôleur qui la contiendrait : le
 * jeu de données de développement est une DONNÉE, pas du code.
 *
 * Ce fichier est donc la seule source des villes fictives. Changer de jeu de
 * test ne demande aucune modification de code — et un test peut injecter son
 * propre jeu via `config(['public_synthetic.cities' => [...]])`.
 *
 * ## Les trois villes
 *
 * Trois scripts, trois contraintes de rendu, pour que les tests d'interface
 * aient un objet réel :
 *
 *  1. `valmora`  — latin, LTR, accents (é, à, ç) : cas nominal ;
 *  2. `wadi_nour` — arabe, RTL : miroir de mise en page, chiffres LTR dans un
 *     contexte RTL, noms longs ;
 *  3. `yesilova` — turc, LTR, caractères spécifiques (ş, ğ, ı, İ) : cas de
 *     casse et de tri (le `i` turc n'est pas le `i` latin).
 *
 * Aucune de ces villes n'existe. Aucun commerce n'est réel. Aucune donnée de
 * production n'est utilisée.
 *
 * ## Ce que la configuration ne peut pas exprimer (constat, pas oubli)
 *
 * Le cahier des charges demande « des commerces avec et sans action de
 * commande ou de contact ». Or `supportedActions()` est porté par le
 * PROVIDER de la verticale, pas par le commerce : tous les restaurants
 * exposent `['contact','order']`, tous les autres `['contact']`. Il n'existe
 * aujourd'hui aucun moyen de déclarer « ce commerce-ci n'accepte pas la
 * commande ». La variation est donc portée par l'état de PUBLICATION
 * (`is_public`), qui est bien per-commerce — et l'écart est consigné dans le
 * rapport de lot plutôt que masqué par une fausse variation.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Garde d'exécution
    |--------------------------------------------------------------------------
    |
    | Les slugs créés par ce générateur sont préfixés et ne peuvent jamais
    | désigner un tenant réel. La commande refuse en outre la production sans
    | `--force`, comme `PilotSeedGuard` (MAT-012 #5870) — mais avec sa PROPRE
    | allowlist : élargir celle des seeds pilotes pour y loger des données de
    | démonstration publique mélangerait deux usages distincts.
    |
    */
    'slug_prefix' => 'pub-synth-',

    /*
    |--------------------------------------------------------------------------
    | Villes fictives
    |--------------------------------------------------------------------------
    |
    | `locale` pilote la langue du tenant ; `script` documente la contrainte de
    | rendu que la ville sert à tester. `country` est un code ISO à deux
    | lettres — fictif dans son association à la ville, réel dans son format,
    | parce que la colonne `companies.country` est un `char(2)`.
    |
    */
    'cities' => [

        'valmora' => [
            'name' => 'Valmora',
            'country' => 'FR',
            'locale' => 'fr',
            'script' => 'latin',
            'timezone' => 'Europe/Paris',
            'currency' => 'EUR',
            // Coordonnées fictives, en pleine terre, pour que la recherche par
            // proximité ait un objet. Elles ne désignent aucun lieu réel.
            'latitude' => 45.7640,
            'longitude' => 4.8357,
            'companies' => [
                [
                    'name' => 'Maison Valmora',
                    'sector' => 'Restauration',
                    'establishments' => [
                        ['name' => 'Maison Valmora — Presqu\'île', 'type' => 'restaurant', 'published' => true],
                        ['name' => 'Maison Valmora — Croix-Rousse', 'type' => 'brasserie', 'published' => true],
                        ['name' => 'Maison Valmora — Confluence', 'type' => 'cafe', 'published' => false],
                    ],
                ],
                [
                    'name' => 'Boulangerie Céleste',
                    'sector' => 'Restauration',
                    'establishments' => [
                        ['name' => 'Boulangerie Céleste — Centre', 'type' => 'patisserie', 'published' => true],
                    ],
                ],
                [
                    'name' => 'Pizzeria Étoile',
                    'sector' => 'Restauration',
                    'establishments' => [
                        ['name' => 'Pizzeria Étoile — Berges', 'type' => 'pizzeria', 'published' => true],
                        ['name' => 'Pizzeria Étoile — Gare', 'type' => 'fast_food', 'published' => true],
                    ],
                ],
            ],
        ],

        'wadi_nour' => [
            'name' => 'وادي النور',
            'country' => 'MA',
            'locale' => 'ar',
            'script' => 'arabic_rtl',
            'timezone' => 'Africa/Casablanca',
            'currency' => 'MAD',
            'latitude' => 33.5731,
            'longitude' => -7.5898,
            'companies' => [
                [
                    'name' => 'مطعم وادي النور',
                    'sector' => 'Restauration',
                    'establishments' => [
                        ['name' => 'مطعم وادي النور — الفرع الرئيسي', 'type' => 'restaurant', 'published' => true],
                        ['name' => 'مطعم وادي النور — فرع الكورنيش', 'type' => 'restaurant', 'published' => true],
                        ['name' => 'مقهى وادي النور', 'type' => 'cafe', 'published' => true],
                    ],
                ],
                [
                    'name' => 'حلويات النخيل',
                    'sector' => 'Restauration',
                    'establishments' => [
                        ['name' => 'حلويات النخيل — السوق المركزي', 'type' => 'patisserie', 'published' => true],
                        ['name' => 'حلويات النخيل — الحي الجديد', 'type' => 'patisserie', 'published' => false],
                    ],
                ],
            ],
        ],

        'yesilova' => [
            'name' => 'Yeşilova',
            'country' => 'TR',
            'locale' => 'tr',
            'script' => 'turkish',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'latitude' => 38.4237,
            'longitude' => 27.1428,
            'companies' => [
                [
                    'name' => 'Yeşilova Sofrası',
                    'sector' => 'Restauration',
                    'establishments' => [
                        ['name' => 'Yeşilova Sofrası — Merkez', 'type' => 'restaurant', 'published' => true],
                        ['name' => 'Yeşilova Sofrası — Sahil', 'type' => 'brasserie', 'published' => true],
                    ],
                ],
                [
                    'name' => 'Işık Fırın',
                    'sector' => 'Restauration',
                    'establishments' => [
                        ['name' => 'Işık Fırın — Çarşı', 'type' => 'patisserie', 'published' => true],
                        ['name' => 'Işık Fırın — İstasyon', 'type' => 'cafe', 'published' => true],
                        ['name' => 'Işık Fırın — Depo', 'type' => 'autre', 'published' => false],
                    ],
                ],
                [
                    'name' => 'Şiş Kebap Evi',
                    'sector' => 'Restauration',
                    'establishments' => [
                        ['name' => 'Şiş Kebap Evi — Çarşı', 'type' => 'fast_food', 'published' => true],
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Seuil de catégories par ville
    |--------------------------------------------------------------------------
    |
    | Le seuil n'est PAS une constante du code : il est écrit dans
    | `public.platform_settings` par la commande, pour que la découverte
    | publique le lise par `PlatformSettingsRepository` exactement comme en
    | production. Une ville peut avoir un seuil différent — c'est ce que la
    | configuration démontre.
    |
    */
    'category_threshold' => [
        'default' => 2,
        'by_city' => [
            'Valmora' => 2,
            'وادي النور' => 1,
            'Yeşilova' => 3,
        ],
    ],
];
