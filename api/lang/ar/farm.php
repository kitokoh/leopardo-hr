<?php

declare(strict_types=1);

// BC-37 AGRICULTURE — Farm OS (#8549-#8570). فهرس الترجمة للوحدة:
// PA2-I18N-007 / issue #5432 — لا نصوص مستخدم مضمّنة في الخلفية،
// كل رسالة تمر عبر __('farm.xxx').
return [
    // قائمة التحقق للخطوة 4 (التهيئة) — الحالة `done` تُقرأ من قاعدة البيانات،
    // ويُترجم هنا العنوان فقط.
    'checklist' => [
        'activity_declared' => 'تم الإعلان عن النشاط الزراعي',
        'tools_decided' => 'الأدوات المفعّلة',
        'solution_active' => 'حل Farm OS مُفعّل',
        'first_site' => 'تم إنشاء أول مستغلة / موقع',
        'first_parcel' => 'تم إنشاء أول قطعة أرض',
        'first_operation' => 'تم تخطيط أول عملية',
        'workers_ready' => 'أداة العمال مفعّلة',
    ],

    // T1.3 — إشعار إسناد عملية (BC-13 COMMS).
    'notifications' => [
        'operation_assigned_title' => 'تم إسناد عملية جديدة',
        'operation_assigned_body' => 'تم إسناد العملية «:title» إليك.',
    ],
];
