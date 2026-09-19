<?php

return [
    'notifications' => [
        'booking_confirmed' => 'Booking confirmed :reference',
        'booking_cancelled' => 'Booking cancelled :reference',
        'ticket_issued' => 'Ticket issued :ticket',
        'reminder_title' => 'Trip reminder',
        'reminder_body' => 'Your trip :code departs at :time.',
    ],
    'reports' => [
        'export_job_queued' => 'Export scheduled',
        'export_job_failed' => 'Export failed',
    ],
    'content' => [
        'article_published' => 'Article published',
        'article_flagged' => 'Article flagged for moderation',
        'comment_pending' => 'Comment awaiting moderation',
    ],
    'console' => [
        'expire_adverts_description' => 'ينهي الإعلانات المنشورة بعد انتهاء صلاحيتها ثم يؤرشف المنتهية القديمة (TRAVEL-908/#6111).',
        'expire_adverts_no_tenant' => 'لا يوجد مستأجر — لا شيء لإنهائه.',
        'expire_adverts_tenant_summary' => 'المستأجر :company: :expired منتهية، :archived مؤرشفة.',
        'expire_adverts_total' => 'الإجمالي: :expired إعلانًا منتهيًا، :archived مؤرشفة.',
    ],
    'staff_assignments' => [
        'duplicate' => 'هذا الموظف معيّن بالفعل لهذا الدور في هذا النطاق.',
    ],
    'distributor_keys' => [
        'rotate_revoked' => 'لا يمكن تدوير مفتاح تم إبطاله.',
    ],
];
