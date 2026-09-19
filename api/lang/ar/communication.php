<?php

return [
    // BC-29 Communication R2 (#7687) — إشعار خطأ مزامنة Gmail.
    'sync_error_title' => 'فشل مزامنة صندوق البريد',
    'sync_error_body' => 'انتهت صلاحية الاتصال بصندوق بريدك :email أو تم إلغاؤه. يرجى إعادة الاتصال لاستئناف المزامنة.',

    // BC-29 Communication R3 (#7688) — تصنيف البريد (الفئات الافتراضية).
    'category_prospect' => 'عميل محتمل',
    'category_client' => 'عميل',
    'category_supplier' => 'مورد',
    'category_invoice' => 'فاتورة',
    'category_commercial' => 'تجاري',
    'category_hr' => 'موارد بشرية',
    'category_spam_newsletter' => 'بريد مزعج / نشرة',
    'category_personal' => 'شخصي',
    'category_urgent' => 'عاجل',
    'category_other' => 'أخرى',

    // BC-29 Communication R3 (#7688) — رسائل واجهة برمجة التطبيقات.
    'category_key_taken' => 'مفتاح الفئة هذا موجود بالفعل لشركتك.',
    'proposal_already_decided' => 'تم البت في اقتراح جهة الاتصال هذا بالفعل.',

    // BC-29 Communication R4 (#7689) — رسائل واجهة برمجة التطبيقات للمتابعات.
    'follow_up_send_scope_required' => 'أعد ربط بريد Gmail الخاص بك مع إذن الإرسال لتفعيل المتابعات التلقائية.',
    'follow_up_not_cancellable' => 'تمت معالجة هذه المتابعة بالفعل ولا يمكن إلغاؤها.',
    'follow_up_mailbox_not_found' => 'صندوق البريد غير موجود.',

    // BC-29 Communication R5 (#7690) — رسائل واجهة الردود المدعومة.
    'reply_mailbox_not_found' => 'صندوق البريد غير موجود.',
    'reply_category_unknown' => 'هذه الفئة غير موجودة في تصنيف شركتك.',
    'reply_auto_category_blocked' => 'الإرسال التلقائي غير مسموح به لهذه الفئة (مالية أو موارد بشرية أو قانونية).',
    'reply_send_scope_required' => 'أعد ربط بريد Gmail الخاص بك مع إذن الإرسال لتفعيل الردود المدعومة.',
    'reply_compose_scope_required' => 'أعد ربط بريد Gmail الخاص بك مع إذن الإنشاء لتفعيل مسودات Gmail.',
    'pending_reply_not_pending' => 'تمت معالجة اقتراح الرد هذا بالفعل.',
    'reply_blocked' => 'لا يمكن إرسال هذا الرد: تم حظره بواسطة إجراء حماية.',
    'reply_rate_limited' => 'يقوم Gmail بتقييد الإرسال حاليًا. يرجى المحاولة بعد قليل.',
    'reply_auth_failed' => 'انتهت صلاحية الاتصال ببريد Gmail الخاص بك. يرجى إعادة ربطه.',
    'reply_send_failed' => 'تعذر إرسال الرد. يرجى المحاولة مرة أخرى.',
];
