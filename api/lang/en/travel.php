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
        'expire_adverts_description' => 'Expires validated adverts past expires_at, then archives old expired ones (TRAVEL-908/#6111).',
        'expire_adverts_no_tenant' => 'No tenant — nothing to expire.',
        'expire_adverts_tenant_summary' => 'Tenant :company: :expired expired, :archived archived.',
        'expire_adverts_total' => 'Total: :expired advert(s) expired, :archived archived.',
        'expire_pending_description' => 'Expires pending TravelAgency bookings past expires_at (TRAVEL-418/#6070).',
        'expire_pending_none' => 'No expired pending booking.',
        'expire_pending_summary' => ':count company(ies) concerned (:processed processed, limit=:limit).',
        'expire_pending_sync' => '[sync] :company expired inline.',
        'expire_pending_tenant' => 'Tenant :company: :count booking(s) expired.',
        'expire_pending_total' => 'Total: :count booking(s) expired.',
    ],
    'marketplace' => [
        'booking_not_found' => 'Marketplace booking not found.',
    ],
];
