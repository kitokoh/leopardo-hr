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
