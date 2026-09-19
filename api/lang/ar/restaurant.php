<?php

return [
    'order' => [
        'quantity_positive' => 'يجب أن تكون الكمية موجبة تمامًا.',
    ],
    'notifications' => [
        'order_ready_title' => 'الطلب جاهز',
        'order_ready_body' => 'الطلب %s جاهز للتقديم (الطاولة %s).',
    ],
    'reservation' => [
        'deposit_exists' => 'يوجد بالفعل وديعة لهذا الحجز.',
        'deposit_on_terminated' => 'لا يمكن تسجيل وديعة على حجز منتهي.',
    ],
    'loyalty' => [
        'opt_in_required' => 'مطلوب الموافقة على قواعد حماية البيانات لتفعيل برنامج الولاء.',
    ],
    'commands' => [
        'outbox_description' => 'يستهلك أحداث صندوق الصادرات المستحقة (مع إعادة المحاولة).',
        'stock_alert_description' => 'ينشر تنبيهات حد المخزون (مرة واحدة يوميًا).',
        'reservation_jobs_description' => 'تذكيرات وعدم حضور الحجوزات.',
        'stock_alert_scan_result' => '%s (%s): تم إنشاء %d تنبيهًا، تم تجاهل %d مكررًا.',
        'stock_alert_total' => 'الإجمالي: تم إنشاء %d تنبيهًا، %d مكررًا.',
    ],
    // RESTO-805 (#6226) / RESTO-902 (#7747) — الطلب العام عبر الإنترنت.
    'public_shop' => [
        'product_unavailable' => 'هذا المنتج غير متاح للطلب عبر الإنترنت.',
        'product_not_served' => 'هذا المنتج لا يُقدَّم في هذا المطعم.',
        'currency_mismatch' => 'عملة المنتج لا تطابق عملة الطلب.',
        'quantity_invalid' => 'يجب أن تكون الكمية موجبة تمامًا.',
        'empty_order' => 'سلة المشتريات فارغة.',
    ],
    // RESTO-902 (#7747) — تقييمات العملاء العامة.
    'public_reviews' => [
        'order_not_eligible' => 'لا يمكن التقييم إلا لطلب تم تقديمه أو توصيله.',
        'already_reviewed' => 'تم إرسال تقييم لهذا الطلب من قبل.',
    ],
];
