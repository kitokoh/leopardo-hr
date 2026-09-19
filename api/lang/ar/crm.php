<?php

return [
    // قنوات التواصل CRM (القضايا 5725/5727)
    'CRM_CHANNEL_NOT_FOUND' => 'قناة CRM غير موجودة في المستأجر الحالي.',
    'CRM_CHANNEL_TYPE_INVALID' => 'نوع قناة CRM غير معروف.',
    'CRM_CHANNEL_NOT_CONFIGURED' => 'قناة CRM نشطة ولكنها غير مهيأة (رمز/مزود مفقود).',
    'CRM_CONSENT_REQUIRED' => 'موافقة التواصل مطلوبة لهذا الشخص وهذه القناة وهذا الغرض.',
    'CRM_QUOTA_EXCEEDED' => 'تم تجاوز الحصة الشهرية للقناة لهذا المستأجر.',
    'CRM_PROVIDER_ERROR' => 'أرجع مزود القناة خطأ.',
    'CRM_WEBHOOK_SIGNATURE_INVALID' => 'توقيع webhook لـ CRM غير صالح.',
    'CRM_WEBHOOK_NOT_CONFIGURED' => 'webhook لـ CRM غير مهيأ (السر مفقود).',
    'CRM_WEBHOOK_VERIFY_INVALID' => 'تم رفض التحقق من اشتراك webhook لـ CRM.',

    'merge' => [
        'unknown_entity' => 'كيان غير معروف (accounts أو contacts أو leads).',
    ],

    'CRM_AUTOMATION_NOT_FOUND' => 'أتمتة CRM غير موجودة في المستأجر الحالي.',
    'CRM_AUTOMATION_INVALID_TRIGGER' => 'حدث تشغيل أتمتة CRM غير معروف.',
    'CRM_AUTOMATION_EMERGENCY_STOPPED' => 'تم إيقاف أتمتة CRM طارئ لهذا المستأجر.',
    'CRM_AUTOMATION_INVALID' => 'أتمتة CRM غير صالحة (قاعدة أو إجراء غير مسموح به).',

    'CRM_EXPORT_NOT_FOUND' => 'مهمة تصدير CRM غير موجودة في المستأجر الحالي.',
    'CRM_EXPORT_NOT_READY' => 'تصدير CRM غير جاهز بعد (قيد المعالجة) أو فشل.',
    'CRM_EXPORT_EXPIRED' => 'انتهت صلاحية تصدير CRM — أنشئ تصديرًا جديدًا.',
    'CRM_EXPORT_ENTITY_UNAVAILABLE' => 'كيان تصدير CRM غير متاح (أساس V0 لم يُدمج بعد في هذه البيئة).',
    'CRM_EXPORT_INVALID_REQUEST' => 'طلب تصدير CRM غير صالح (كيان أو عمود غير مسموح به).',
    'CRM_EXPORT_FAILED' => 'فشل إنشاء تصدير CRM.',
    'CRM_EXPORT_ENTITY_INVALID' => 'كيان تصدير CRM غير معروف.',

    // #7751 — campagnes email effectives (tranche 1 Marketing & Communication)
    'campaigns' => [
        'email_requires_subject_body' => 'تتطلب حملة البريد الإلكتروني موضوعًا ونص رسالة قبل البدء.',
    ],
    'console' => [
        'process_sends_description' => 'يبدأ حملات البريد الإلكتروني المجدولة المستحقة ويعالج الإرسالات المعلقة للحملات الجارية.',
        'process_sends_tables_missing' => 'crm:process-campaign-sends — جداول الحملات غير موجودة، لا شيء للقيام به.',
        'process_sends_summary' => 'crm:process-campaign-sends — المستحقة: :due، الجارية: :running، المرسلة: :dispatched، الإخفاقات: :failed.',
    ],

];
