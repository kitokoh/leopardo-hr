<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Observabilité RestaurantManager — seuils d'alerte (#9742)
|--------------------------------------------------------------------------
|
| `GET /api/v1/restaurant/health/metrics` expose des compteurs. Un compteur
| ne prévient personne : ce fichier porte les SEUILS au-delà desquels le
| endpoint émet une alerte actionnable dans `data.alerts`.
|
| Chaque seuil est surchargeable par variable d'environnement, pour qu'un
| restaurant à fort volume puisse tolérer plus de commandes en cuisine qu'un
| food-truck sans avoir à redéployer du code.
|
| `severity` : `critical` = perte d'argent ou de message métier, à traiter
| immédiatement ; `warning` = dérive à surveiller dans la journée.
|
*/

return [

    'alerts' => [

        // Un événement d'outbox en échec = un message métier non délivré
        // (confirmation de commande, ticket cuisine, notification client).
        'outbox_failed' => [
            'threshold' => (int) env('RESTO_ALERT_OUTBOX_FAILED', 1),
            'severity' => 'critical',
            'message' => 'Des événements métier n\'ont pas pu être publiés.',
        ],

        // Dead-letter : les tentatives sont épuisées, aucun rejeu automatique.
        'outbox_dead_letter' => [
            'threshold' => (int) env('RESTO_ALERT_OUTBOX_DEAD_LETTER', 1),
            'severity' => 'critical',
            'message' => 'Des événements sont en lettre morte et ne seront plus rejoués.',
        ],

        // Encaissé mais non servi : litige client en formation.
        'orders_paid_unserved' => [
            'threshold' => (int) env('RESTO_ALERT_PAID_UNSERVED', 1),
            'severity' => 'critical',
            'message' => 'Des commandes sont payées mais non servies.',
        ],

        // Commandes bloquées en cuisine au-delà du seuil de temps.
        'orders_stuck_kitchen' => [
            'threshold' => (int) env('RESTO_ALERT_STUCK_KITCHEN', 1),
            'severity' => 'warning',
            'message' => 'Des commandes stagnent en cuisine au-delà du seuil.',
        ],

        // Modération d'avis en retard : la réputation publique se dégrade.
        'reviews_pending' => [
            'threshold' => (int) env('RESTO_ALERT_REVIEWS_PENDING', 25),
            'severity' => 'warning',
            'message' => 'La file de modération des avis s\'accumule.',
        ],

        // Jobs en échec (compteur GLOBAL, non cloisonné par tenant).
        'jobs_failed' => [
            'threshold' => (int) env('RESTO_ALERT_JOBS_FAILED', 10),
            'severity' => 'warning',
            'message' => 'La file de jobs accumule des échecs.',
        ],
    ],
];
