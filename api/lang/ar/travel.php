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
        'expire_pending_description' => 'ينهي حجوزات TravelAgency المعلقة التي انتهت صلاحيتها (TRAVEL-418/#6070).',
        'expire_pending_none' => 'لا يوجد حجز معلّق منتهي الصلاحية.',
        'expire_pending_summary' => 'عدد الشركات المعنية: :count (:processed معالجة، limit=:limit).',
        'expire_pending_sync' => '[sync] انتهت صلاحية :company مباشرة.',
        'expire_pending_tenant' => 'المستأجر :company: :count حجزًا منتهي الصلاحية.',
        'expire_pending_total' => 'الإجمالي: :count حجزًا منتهي الصلاحية.',
    ],
    'marketplace' => [
        'booking_not_found' => 'حجز السوق غير موجود.',
    ],
];
