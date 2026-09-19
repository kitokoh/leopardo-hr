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
        'expire_adverts_description' => 'Süresi geçen onaylı ilanları sona erdirir, eski süresi geçenleri arşivler (TRAVEL-908/#6111).',
        'expire_adverts_no_tenant' => 'Kiracı yok — sona erdirilecek bir şey yok.',
        'expire_adverts_tenant_summary' => 'Kiracı :company: :expired süresi geçti, :archived arşivlendi.',
        'expire_adverts_total' => 'Toplam: :expired ilan süresi geçti, :archived arşivlendi.',
    ],
    'marketplace' => [
        'booking_not_found' => 'Pazar yeri rezervasyonu bulunamadı.',
    ],
];
