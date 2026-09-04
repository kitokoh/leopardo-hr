<?php

return [
    // Artisan commands (BC-26-D07, issue #6295)
    'commands' => [
        'route_not_found' => 'Route #:route not found for this tenant.',
        'close_route_planned' => 'Closure of route #:route scheduled (async job).',
        'no_deliveries_for_tenant' => 'No deliveries for this tenant (:company).',
        'export_report_planned' => 'Report export scheduled (:from → :to).',
        'dlq_replayed' => 'Delivery DLQ — replayed: :replayed, failures: :failed.',
    ],
];
