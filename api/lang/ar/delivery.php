<?php

return [
    // أوامر Artisan (BC-26-D07, issue #6295)
    'commands' => [
        'route_not_found' => 'المسار #:route غير موجود لهذا المستأجر.',
        'close_route_planned' => 'تمت جدولة إغلاق المسار #:route (مهمة غير متزامنة).',
        'no_deliveries_for_tenant' => 'لا توجد تسليمات لهذا المستأجر (:company).',
        'export_report_planned' => 'تمت جدولة تصدير التقرير (:from ← :to).',
        'dlq_replayed' => 'Delivery DLQ — أعيد تشغيلها: :replayed، الإخفاقات: :failed.',
    ],
];
