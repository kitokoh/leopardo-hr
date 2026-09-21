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
        'expire_pending_description' => 'Süresi geçen bekleyen TravelAgency rezervasyonlarını sona erdirir (TRAVEL-418/#6070).',
        'expire_pending_none' => 'Süresi geçmiş bekleyen rezervasyon yok.',
        'expire_pending_summary' => 'İlgili şirket sayısı: :count (:processed işlendi, limit=:limit).',
        'expire_pending_sync' => '[sync] :company satır içinde sona erdirildi.',
        'expire_pending_tenant' => 'Kiracı :company: :count rezervasyon sona erdirildi.',
        'expire_pending_total' => 'Toplam: :count rezervasyon sona erdirildi.',
    ],
    'marketplace' => [
        'booking_not_found' => 'Pazar yeri rezervasyonu bulunamadı.',
    ],
];
