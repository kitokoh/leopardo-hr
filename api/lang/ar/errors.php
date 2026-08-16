<?php

return [
    // Auth
    'INVALID_CREDENTIALS' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
    'ACCOUNT_SUSPENDED' => 'تم تعليق حسابك. تواصل مع المسؤول.',
    'ACCOUNT_ARCHIVED' => 'هذا الحساب مؤرشف.',
    'TOKEN_EXPIRED' => 'انتهت صلاحية جلستك. يرجى تسجيل الدخول مجدداً.',
    'TOO_MANY_ATTEMPTS' => 'محاولات كثيرة جداً. أعد المحاولة بعد :minutes دقائق.',
    'EMPLOYEE_NOT_ACTIVE' => 'حساب الموظف غير نشط.',
    'COMPANY_NOT_FOUND' => 'الشركة غير موجودة.',
    'INVALID_CURRENT_PASSWORD' => 'كلمة المرور الحالية غير صحيحة.',
    'UNAUTHENTICATED' => 'يجب تسجيل الدخول.',

    // Pointage
    'ALREADY_CHECKED_IN' => 'لقد سجلت حضورك بالفعل اليوم.',
    'MISSING_CHECK_IN' => 'يجب تسجيل الحضور أولاً قبل تسجيل الانصراف.',
    'ALREADY_CHECKED_OUT' => 'لقد سجلت انصرافك بالفعل اليوم.',
    'PUNCH_PHOTO_REQUIRED' => 'الصورة مطلوبة لتسجيل الحضور أو الانصراف في شركتك.',

    // Finance
    'PLAN_CAMERAS_REQUIRED' => 'خطتك لا تتضمن وحدة الكاميرات. قم بالترقية إلى خطة Business.',
    'MAX_CAMERAS_REACHED' => 'تم بلوغ حد :limit كاميرا لخطتك.',
    'PLAN_FINANCE_REQUIRED' => 'خطتك لا تتضمن وحدة المالية.',
    'FINANCE_MAX_DOCS_REACHED' => 'تم بلوغ حد :limit مستند هذا الشهر.',
    'INVOICE_ALREADY_SENT' => 'تم إرسال هذه الفاتورة بالفعل ولا يمكن تعديلها.',

    // Invitations
    'INVITATION_ALREADY_ACCEPTED' => 'تم قبول هذه الدعوة بالفعل.',
    'INVITATION_EXPIRED' => 'انتهت صلاحية هذه الدعوة.',
    'INVITATION_NOT_FOUND' => 'الدعوة غير موجودة.',

    // Biometric
    'CAMERA_TOKEN_EXPIRED' => 'انتهت صلاحية الوصول إلى هذه الكاميرا.',
    'CAMERA_TOKEN_REVOKED' => 'تم إلغاء هذا الوصول.',

    // Payroll
    'PAYROLL_BALANCE_UNAVAILABLE' => 'رصيد الموظف غير متوفر مؤقتًا. يرجى المحاولة مرة أخرى بعد قليل.',
    // Général
    'NOT_FOUND' => 'المورد غير موجود.',
    'FORBIDDEN' => 'ليس لديك صلاحية لهذا الإجراء.',
    'SERVER_ERROR' => 'حدث خطأ. يرجى المحاولة مجدداً.',
    'VALIDATION_ERROR' => 'بعض الحقول غير صحيحة.',
    'BAD_REQUEST' => 'طلب غير صالح.',
    'CONFLICT' => 'تعارض في البيانات يمنع هذه العملية.',
    'VALIDATION_FAILED' => 'بعض الحقول غير صحيحة.',
    'TOO_MANY_REQUESTS' => 'طلبات كثيرة جداً. حاول لاحقاً.',
    'SERVICE_UNAVAILABLE' => 'الخدمة غير متوفرة مؤقتاً.',
    'HTTP_ERROR' => 'حدث خطأ. يرجى المحاولة مجدداً.',
    'UNSUPPORTED_API_VERSION' => 'إصدار API غير مدعوم.',

    'PAYMENT_SESSION_FAILED' => 'تعذّر إنشاء جلسة الدفع.',
    'NO_PAYMENT_ACCOUNT' => 'لا يوجد حساب دفع مرتبط. اشترك في باقة أولاً.',
    'VERIFICATION_CODE_SENT' => 'تم إرسال رمز التحقق.',
    'VERIFICATION_TEMPORARILY_UNAVAILABLE' => 'التحقق من طلبك غير متاح مؤقتاً. حاول مرة أخرى بعد قليل.',
    'TRIAL_SPACE_READY' => 'مساحة Leopardo الخاصة بك جاهزة!',
    'SESSION_ALREADY_OPEN' => 'توجد جلسة مفتوحة بالفعل لهذا الموظف.',
    'OUTSIDE_GEOFENCE' => 'الموقع خارج منطقة الحضور.',
    'ATTENDANCE_MODE_PERSONALIZATION_DISABLED' => 'تخصيص وضع التسجيل معطّل.',
    'PREFERENCE_UPDATED' => 'تم تحديث التفضيل.',
    'CONFIG_UPDATED' => 'تم تحديث الإعدادات.',

    'PAYOUT_REQUEST_REFUSED' => 'تم رفض طلب الدفع.',
    'PAYOUT_REQUEST_FAILED' => 'حدث خطأ أثناء طلب الدفع.',
    'COMPANY_MODE_FORCED' => 'تفرض شركتك وضع تسجيل حضور ولا يمكنك تغييره.',
    'GPS_CONSENT_REQUIRED' => 'موافقة GPS إلزامية لتفعيل تسجيل الحضور التلقائي.',
    'PAYMENT_BATCH_RUN_INVALID' => 'يجب حساب دورة الرواتب أو التحقق منها قبل إنشاء دفعة.',
    'PAYMENT_BATCH_CREATED' => 'تم الإعلان عن الدفعة. تتم معالجة تأكيدات الموظفين والمستندات في الخلفية.',
    'ARCHIVED_DOCUMENT_NOT_FOUND' => 'المستند المؤرشف غير موجود.',
    'TOO_MANY_PENDING_REQUESTS' => 'لديك بالفعل 3 طلبات معلقة.',
    'SAML_RESPONSE_MISSING' => 'SAMLResponse مفقود.',
    'SAML_ASSERTION_RECEIVED' => 'تم استلام تأكيد SAML.',
    'OIDC_CODE_MISSING' => 'الرمز أو id_token مفقود.',
    'OIDC_LOGIN_SUCCESS' => 'تم تسجيل الدخول عبر OIDC بنجاح.',
    'TENANT_COUNTRY_REQUIRED' => 'البلد القانوني للمستأجر إلزامي ويجب أن يكون مدعوماً قبل هذه العملية.',
    'TENANT_COUNTRY_INVALID' => 'بلد المستأجر مفقود أو غير مدعوم (:country).',
    'FILE_TOO_LARGE' => 'يتجاوز الملف المرفوع الحد الأقصى المسموح به.',
    'CONTRACT_ACTIVATION_INVALID_STATE' => 'يمكن تفعيل العقود المسودة فقط.',
    'CONTRACT_SUSPENSION_INVALID_STATE' => 'يمكن تعليق العقود النشطة فقط.',
    'CONTRACT_TERMINATION_INVALID_STATE' => 'يجب أن يكون العقد نشطاً أو معلقاً لإنهائه.',

    // Trial signup (audit #4395)
    'ALREADY_PROCESSED' => 'تمت معالجة طلب التجربة هذا بالفعل.',
    'INVALID_OR_EXPIRED_CODE' => 'رمز التحقق غير صالح أو منتهي الصلاحية.',
    'EMAIL_ALREADY_REGISTERED' => 'يوجد حساب بهذا البريد الإلكتروني بالفعل. سجّل الدخول مباشرة.',
    'INVALID_COUNTRY' => 'بلد التسجيل غير صالح أو غير مدعوم. يرجى إعادة بدء التسجيل.',
    'NO_PLAN_AVAILABLE' => 'خدمة التجربة غير متاحة مؤقتًا.',
    'PROVISIONING_FAILED' => 'حدث خطأ أثناء إنشاء مساحة العمل الخاصة بك. يرجى المحاولة مرة أخرى.',
    'PAYROLL_ALREADY_VALIDATED' => 'تم التحقق من هذه دفعة الأجور بالفعل ولا يمكن تعديلها.',
    'PAYROLL_RUN_LOCKED' => 'دفعة الأجور هذه مقفلة (إقفال محاسبي) ولا يمكن تعديلها.',
    'PAYROLL_RUN_NOT_LOCKED' => 'يمكن تسوية دفعة أجور مقفلة فقط.',
    'PAYROLL_RUN_NOT_VALIDATED' => 'يجب التحقق من دفعة الأجور (خطوة الموارد البشرية) قبل الإقفال المحاسبي.'
];
