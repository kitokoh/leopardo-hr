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

    // API responses (#3237)
    'RESET_LINK_SENT' => 'إذا وُجد حساب لهذا البريد، تم إرسال رابط إعادة التعيين.',
    'INVALID_RESET_TOKEN' => 'رمز إعادة التعيين غير صالح أو منتهي الصلاحية أو مستخدم مسبقاً.',
    'PASSWORD_RESET_SUCCESS' => 'تمت إعادة تعيين كلمة المرور. سجّل الدخول بكلمة المرور الجديدة.',
    'ACCOUNT_SUSPENDED_CONTACT_ADMIN' => 'تم تعليق حسابك. يرجى التواصل مع المسؤول.',
    'EMAIL_ALREADY_USED' => 'عنوان البريد هذا مستخدم بالفعل.',
    'TWO_FA_ALREADY_ENABLED' => 'المصادقة الثنائية مفعّلة بالفعل لهذا الحساب.',
    'PAYMENT_SESSION_FAILED' => 'تعذّر إنشاء جلسة الدفع.',
    'NO_PAYMENT_ACCOUNT' => 'لا يوجد حساب دفع مرتبط. اشترك في باقة أولاً.',
    'VERIFICATION_CODE_SENT' => 'تم إرسال رمز التحقق.',
    'VERIFICATION_TEMPORARILY_UNAVAILABLE' => 'التحقق من طلبك غير متاح مؤقتاً. حاول مرة أخرى بعد قليل.',
    'TRIAL_SPACE_READY' => 'مساحة Leopardo الخاصة بك جاهزة!',
    'EVALUATION_ALREADY_EXISTS' => 'يوجد تقييم مسبق لهذا الموظف في هذه الفترة.',
    'EVALUATION_ACKNOWLEDGED_IMMUTABLE' => 'لا يمكن تعديل تقييم تم استلامه.',
    'EVALUATION_DRAFT_ONLY_SUBMIT' => 'يمكن إرسال التقييمات المسودة فقط.',
    'EVALUATION_SUBMITTED_ONLY_ACK' => 'يمكن استلام التقييمات المرسلة فقط.',
    'EVALUATION_DRAFT_ONLY_DELETE' => 'يمكن حذف التقييمات المسودة فقط.',
    'PAYROLL_RUN_VALIDATE_BEFORE_SEND' => 'يجب التحقق من دورة الرواتب قبل الإرسال.',
    'SESSION_ALREADY_OPEN' => 'توجد جلسة مفتوحة بالفعل لهذا الموظف.',
    'OUTSIDE_GEOFENCE' => 'الموقع خارج منطقة الحضور.',
    'ATTENDANCE_MODE_PERSONALIZATION_DISABLED' => 'تخصيص وضع التسجيل معطّل.',
    'PREFERENCE_UPDATED' => 'تم تحديث التفضيل.',
    'CONFIG_UPDATED' => 'تم تحديث الإعدادات.',
];
