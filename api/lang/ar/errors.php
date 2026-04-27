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

    // Général
    'NOT_FOUND' => 'المورد غير موجود.',
    'FORBIDDEN' => 'ليس لديك صلاحية لهذا الإجراء.',
    'SERVER_ERROR' => 'حدث خطأ. يرجى المحاولة مجدداً.',
    'VALIDATION_ERROR' => 'بعض الحقول غير صحيحة.',
];
