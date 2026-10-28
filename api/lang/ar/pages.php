<?php

/**
 * BC-39 PAGES — رسائل الخادم (ar).
 *
 * تطابق المفاتيح مع fr/en/tr تفرضه check-i18n-catalog-parity.sh.
 */
return [
    // #8723 — النشر والربط بالمؤسسة
    'publish_seo_title_required' => 'لا يمكن نشر صفحة بدون عنوان SEO: ستنتج رابطًا قابلاً للفهرسة دون عنوان.',
    'site_not_found' => 'المؤسسة غير موجودة لهذه الصفحة (site_id=:site).',
    'site_cross_tenant' => 'المؤسسة :site لا تنتمي إلى الشركة :company: لا يمكن ربط الصفحة بموقع مستأجر آخر.',

    // #8724 — سجل الكتل
    'block_type_duplicate' => 'نوع الكتلة «:type» مقدم بالفعل من السياق «:owner»: لا يمكن لـ «:new» تسجيله مرة أخرى.',

    // #8725 — ملف التحقق
    'verification_method_disabled' => 'طريقة التحقق «:method» غير مفعّلة على هذه المنصة.',
    'verification_already_open' => 'يوجد ملف تحقق مفتوح بالفعل لهذه الصفحة.',
    'verification_closed' => 'هذا الملف مغلق: افتح ملفًا جديدًا لطلب تحقق آخر.',
    'verification_suspended' => 'لا يمكن تقديم صفحة موقوفة لطلب التحقق.',
    'verification_wrong_method' => 'تتطلب هذه العملية الطريقة «:expected» (الملف في «:actual»).',
    'verification_cooldown' => 'تم إرسال رمز للتو: أعد المحاولة بعد :seconds ثانية.',
    'verification_code_not_found' => 'لم يصدر أي رمز لهذا الرقم في هذا الملف.',
    'verification_code_expired' => 'انتهت صلاحية هذا الرمز: اطلب رمزًا جديدًا.',
    'verification_code_consumed' => 'تم استخدام هذا الرمز بالفعل.',
    'verification_code_locked' => 'محاولات كثيرة: اطلب رمزًا جديدًا.',
    'verification_code_mismatch' => 'رمز غير صحيح (:remaining محاولة متبقية).',
    'verification_no_evidence' => 'لا يمكن اعتماد ملف مستندي دون أي مستند داعم.',
    'verification_evidence_invalid' => 'تم رفض المستند الداعم: :errors',
    'verification_evidence_path_public' => 'لا يمكن أن يكون مسار تخزين المستند رابطًا عامًا أو مسارًا مطلقًا.',
    'verification_reason_invalid' => 'سبب القرار غير معروف: «:reason».',
    'verification_transition_invalid' => 'انتقال تحقق غير ممكن: :from -> :to.',

    // #8726 — النطاقات المخصصة
    'domain_invalid' => 'هذا النطاق غير صالح: :reason',
    'domain_taken' => 'هذا النطاق مرتبط بالفعل بصفحة. إذا كنت مالكه، فاتصل بالدعم.',
    'domain_already_linked' => 'هذا النطاق مرتبط بالفعل بهذه الصفحة.',
    'domain_entitlement_missing' => 'النطاقات المخصصة غير مشمولة في عرضك الحالي.',
    'domain_quota_exceeded' => 'يتيح عرضك :limit نطاقًا مخصصًا.',
    'domain_removed' => 'تم حذف هذا النطاق ولم يعد ممكنًا التحقق منه.',
    'domain_plan_expired' => 'انتهى اشتراكك: النطاقات المخصصة معطّلة حتى التجديد.',

    // #8730 — rendu public SSR
    'public' => [
        'verified' => 'منشأة موثّقة',
        'contact' => 'اتصال',
        'map' => 'عرض على الخريطة',
        'actions' => [
            'directions' => 'الذهاب',
            'contact' => 'مراسلة',
        ],
    ],
];
