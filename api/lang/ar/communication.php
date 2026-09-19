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
];
