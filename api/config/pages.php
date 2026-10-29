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

    /*
    |--------------------------------------------------------------------------
    | Double lecture Showcase -> Pages (#8727)
    |--------------------------------------------------------------------------
    |
    | Le backfill (`showcase:pages-backfill`) recopie les vitrines vers Pages,
    | mais tant que la source maître n'est pas basculée, `company_showcases`
    | reste la source de vérité. Cette section règle la période de DOUBLE
    | LECTURE : chaque requête publique lit le legacy, puis relit la Page
    | correspondante et compare les deux. Aucune réponse n'est modifiée — la
    | double lecture n'est qu'une observation, et c'est délibéré : une lecture
    | qui changerait la réponse rendrait impossible d'attribuer une divergence
    | au legacy ou à la projection.
    |
    | `rollout_percent` est un pourcentage déterministe par slug : un visiteur
    | qui retombe sur la même vitrine voit toujours le même verdict, et monter
    | le pourcentage ne fait qu'ajouter des vitrines au panel (jamais en
    | retirer). Monté sans réglage explicite, le rollout vaut 0 : rien n'est
    | doublé.
    |
    */
    'double_read' => [
        'enabled' => (bool) env('PAGES_DOUBLE_READ_ENABLED', false),
        'rollout_percent' => (int) env('PAGES_DOUBLE_READ_ROLLOUT_PERCENT', 0),

        // Une divergence est-elle journalisée en `warning` (true) ou
        // seulement comptée (false) ? Une divergence n'est pas une erreur :
        // elle signale un écart de projection à arbitrer avant bascule.
        'log_divergences' => (bool) env('PAGES_DOUBLE_READ_LOG_DIVERGENCES', true),
    ],
];
