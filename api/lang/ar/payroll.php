<?php

return [
    'zero_slips_generated' => "لم يتم إنشاء أي كشف راتب: تأكد من وجود هيكل رواتب نشط واحد على الأقل لهذا البلد قبل حساب الرواتب.",
    'rate_edit_locked' => "لا يمكن تعديل صف مُرسل أو نشط أو مستبدل — اقترح تغييراً جديداً.",
    'rate_delete_draft_only' => "يمكن حذف صف مسودة فقط.",
    'rate_country_unsupported' => "بلد غير مدعوم.",
    'tax_scale_default_name' => ":country مقياس ضريبي قانوني :year",

    // Issue #1923 — سير عمل التحقق من المعدلات القانونية (#1813): رسائل
    // الخدمة/المستمع/وحدات التحكم الإدارية — لا مزيد من السلاسل الثابتة.
    'rate_submit_draft_only' => "يمكن إرسال صف المسودة فقط (الحالة الحالية: :status).",
    'rate_approve_pending_only' => "يمكن اعتماد صف قيد الانتظار فقط (الحالة الحالية: :status).",
    'rate_reject_pending_only' => "يمكن رفض صف قيد الانتظار فقط (الحالة الحالية: :status).",
    'rate_reject_reason_required' => "سبب الرفض إلزامي.",
    'rate_table_unknown' => "جدول غير معروف.",
    'rate_overlap_conflict' => "يوجد بالفعل صف نشط لنفس الهوية في فترة تتداخل مع نافذة السريان الجديدة: أغلق نافذة الصف الحالي أولاً.",
    'rate_validation_requested_title' => "طلب التحقق من المعدل — :label",
    'rate_validation_requested_body' => "بانتظار تحققك من :kind القانوني (:label) في واجهة الإدارة.",
    'rate_kind_tax_scale' => "مقياس ضريبي",
    'rate_kind_contribution' => "معدل اشتراك",
    'rate_approved_title' => "تم اعتماد تغيير المعدل",
    'rate_approved_body' => "تم اعتماد تغيير المعدل القانوني (:label) وهو نشط الآن.",
    'rate_rejected_title' => "تم رفض تغيير المعدل",
    'rate_rejected_body' => "تم رفض تغيير المعدل القانوني (:label): :reason",
];
