<?php

declare(strict_types=1);

// BC-32 HOSPITALITY (HOSP-001..008, EPIC #7951) — رسائل المستخدم لعمود
// HospitalityManager (PA2-I18N-007 : كل الرسائل عبر هذا الفهرس).
return [
    'solution_inactive' => 'حل HospitalityManager غير مفعّل لهذا المستأجر.',
    'no_availability' => 'لا يوجد توفر لهذا النوع من الغرف في هذه الفترة.',
    'invalid_transition' => 'انتقال حجز غير صالح (:from → :target).',
    'console' => [
        'expire_pending_description' => 'إنهاء حجوزات الضيافة المعلّقة المتجاوزة: إلغاء + تحرير المخزون (HOSP-004/#7946).',
        'expire_pending_no_tenant' => 'لا يوجد مستأجر نشط — لا شيء لإنهائه.',
        'expire_pending_tenant_summary' => 'المستأجر :company : :count حجز ضيافة منتهٍ.',
        'expire_pending_total' => 'الإجمالي : :count حجز ضيافة منتهٍ.',
    ],
];
