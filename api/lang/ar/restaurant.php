<?php

return [
    'order' => [
        'quantity_positive' => 'يجب أن تكون الكمية موجبة تمامًا.',
    ],
    'notifications' => [
        'order_ready_title' => "الطلب جاهز",
        'order_ready_body' => "الطلب %s جاهز للتقديم (الطاولة %s).",
    ],
    'reservation' => [
        'deposit_exists' => "يوجد بالفعل وديعة لهذا الحجز.",
        'deposit_on_terminated' => "لا يمكن تسجيل وديعة على حجز منتهي.",
    ],
    'loyalty' => [
        'opt_in_required' => "مطلوب الموافقة على قواعد حماية البيانات لتفعيل برنامج الولاء.",
    ],
    'commands' => [
        'outbox_description' => "يستهلك أحداث صندوق الصادرات المستحقة (مع إعادة المحاولة).",
        'stock_alert_description' => "ينشر تنبيهات حد المخزون (مرة واحدة يوميًا).",
        'reservation_jobs_description' => "تذكيرات وعدم حضور الحجوزات.",
        'stock_alert_scan_result' => "%s (%s): تم إنشاء %d تنبيهًا، تم تجاهل %d مكررًا.",
        'stock_alert_total' => "الإجمالي: تم إنشاء %d تنبيهًا، %d مكررًا.",
    ],
];
