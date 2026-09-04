<?php

return [
    // Artisan komutları (BC-26-D07, issue #6295)
    'commands' => [
        'route_not_found' => 'Bu tenant için #:route rotası bulunamadı.',
        'close_route_planned' => '#:route rotasının kapanışı planlandı (asenkron iş).',
        'no_deliveries_for_tenant' => 'Bu tenant için teslimat yok (:company).',
        'export_report_planned' => 'Rapor dışa aktarımı planlandı (:from → :to).',
        'dlq_replayed' => 'Delivery DLQ — yeniden oynatıldı: :replayed, hatalar: :failed.',
    ],
];
