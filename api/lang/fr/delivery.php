<?php

return [
    // Commandes artisan (BC-26-D07, issue #6295)
    'commands' => [
        'route_not_found' => 'Tournée #:route introuvable pour ce tenant.',
        'close_route_planned' => 'Clôture de la tournée #:route planifiée (job asynchrone).',
        'no_deliveries_for_tenant' => 'Aucune livraison pour ce tenant (:company).',
        'export_report_planned' => 'Export rapport planifié (:from → :to).',
        'dlq_replayed' => 'DLQ Delivery — rejouées : :replayed, échecs : :failed.',
    ],
];
