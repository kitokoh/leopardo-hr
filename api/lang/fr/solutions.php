<?php

declare(strict_types=1);

/**
 * Labels i18n serveur des solutions sectorielles (rendu PDF).
 *
 * Le wizard web localise via SOLUTION_LABELS côté front ; le PDF étant
 * généré par le backend, il utilise les fichiers lang Laravel (même clés).
 */

return [
    'restaurant' => [
        'package' => [
            'mobile_employee' => 'App mobile employé',
            'mobile_manager' => 'App mobile manager',
            'attendance_mobile' => 'Pointage mobile géolocalisé',
            'kiosk' => 'Kiosque de pointage (borne)',
            'edge' => 'Nœud Edge local (offline-first)',
            'planning' => 'Planning d\'équipe',
            'payroll' => 'Paie (multi-pays)',
            'accounting' => 'Comptabilité',
            'delivery' => 'Gestion de la livraison',
            'reservations' => 'Réservations en ligne',
            'inventory' => 'Gestion de stock',
            'loyalty' => 'Fidélité & marketing',
            'pos' => 'Caisse (POS) connectée',
        ],
        'reason' => [
            'base' => 'Indispensable : vos salariés pointent, consultent leurs fiches et leurs paies.',
            'manager' => 'Votre équipe est assez grande pour piloter depuis le mobile.',
            'attendance_mobile' => 'Vous avez choisi le pointage mobile.',
            'kiosk' => 'Vous avez choisi un pointage sur borne.',
            'edge' => 'Le kiosque fonctionne même sans connexion grâce au nœud local.',
            'scheduling' => 'Vous gérez des plannings d\'équipe.',
            'payroll' => 'Vous voulez internaliser la paie.',
            'accounting' => 'Vous avez demandé un suivi comptable.',
            'delivery' => 'Vous faites de la livraison.',
            'reservations' => 'Vous prenez des réservations.',
            'inventory' => 'Vous voulez suivre votre stock.',
            'loyalty' => 'Vous voulez fidéliser vos clients.',
            'pos' => 'Vous voulez une caisse connectée.',
        ],
    ],
    'trade' => [
        'package' => [
            'trade_workspace' => 'Espace Trade (dossiers, documents, historique)',
            'importer_workspace' => 'Espace acheteur : demandes de prix et comparaison d\'offres',
            'exporter_workspace' => 'Espace vendeur : catalogue, offres, devis et pro forma',
            'wholesale_workspace' => 'Espace grossiste : catalogue et clients professionnels',
            'retail_workspace' => 'Espace vente au détail',
            'freight_quotes' => 'Cotations transport et transit',
            'customs_assist' => 'Aide à la préparation douanière',
            'quality_control' => 'Contrôle qualité & inspection',
            'mobile_field' => 'App mobile terrain (bientôt disponible)',
        ],
        'reason' => [
            'base' => 'Indispensable : vos dossiers, documents et échanges restent au même endroit.',
            'importer' => 'Vous achetez à l\'étranger : demandes de prix et comparaison d\'offres.',
            'exporter' => 'Vous vendez à l\'étranger : catalogue, offres et documents commerciaux.',
            'both' => 'Vous importez et exportez : les deux espaces sont activés.',
            'wholesaler' => 'Vous vendez en gros : catalogue, clients professionnels et offres.',
            'retailer' => 'Vous vendez au détail sur votre marché local.',
            'freight' => 'Vos flux impliquent du transport : demandez des cotations aux prestataires.',
            'customs' => 'Vous avez demandé une aide à la préparation douanière.',
            'quality' => 'Vous souhaitez suivre le contrôle qualité et l\'inspection.',
        ],
    ],
    'pdf' => [
        'title' => 'Votre pack Leopardo',
        'empty' => 'Aucun élément sélectionné.',
        'next_steps' => 'Prochaines étapes',
        'next_step_account' => 'Créez votre espace Leopardo (essai gratuit, sans carte bancaire).',
        'next_step_install' => 'Installez les apps mobiles (QR codes sur la page de téléchargement).',
        'next_step_edge' => 'Installez le nœud Edge local si vous avez choisi le kiosque de pointage.',
        'footer' => 'Document généré automatiquement — leopardo-hr. Pack modifiable à tout moment dans votre espace.',
    ],
];
