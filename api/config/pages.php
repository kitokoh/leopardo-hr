<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Vérification de propriété des Pages (BC PAGES, #8725)
    |--------------------------------------------------------------------------
    |
    | La disponibilité des méthodes est une décision PRODUIT, pas technique :
    | un SMS a plus ou moins de valeur de preuve selon le pays, et les pièces
    | acceptées diffèrent (bail notarié au Sénégal, Handelsregister en
    | Allemagne). La configuration permet de restreindre l'ensemble sans
    | redéploiement — et une configuration vide désactive tout (fail-closed),
    | ce qui se voit immédiatement en recette.
    |
    */
    'verification' => [
        'methods' => array_filter(array_map(
            'trim',
            explode(',', (string) env('PAGES_VERIFICATION_METHODS', 'sms,document,superadmin')),
        )),

        // Pays où la vérification est REQUISE avant publication. Ailleurs, la
        // Page peut être publiée « non vérifiée » avec la mention publique
        // correspondante (axe publication ≠ axe vérification, #8723).
        'required_countries' => array_filter(array_map(
            'trim',
            explode(',', (string) env('PAGES_VERIFICATION_REQUIRED_COUNTRIES', '')),
        )),

        // Conservation des pièces justificatives, en mois (minimisation RGPD).
        'evidence_retention_months' => (int) env('PAGES_EVIDENCE_RETENTION_MONTHS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Publication
    |--------------------------------------------------------------------------
    */
    'publication' => [
        // Domaines personnalisés : premium, soumis à entitlement (#8726).
        'custom_domains_enabled' => (bool) env('PAGES_CUSTOM_DOMAINS_ENABLED', false),

        // Sitemap : nombre maximal d'URL par fichier (protocole sitemap : 50 000).
        'sitemap_max_urls' => (int) env('PAGES_SITEMAP_MAX_URLS', 50000),
    ],
];
