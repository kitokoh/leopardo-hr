<?php

return [
    // BC-29 Communication R2 (#7687) — Gmail eşitleme hatası bildirimi.
    'sync_error_title' => 'Posta kutusu eşitlemesi başarısız oldu',
    'sync_error_body' => ':email posta kutunuzun bağlantısının süresi doldu veya iptal edildi. Eşitlemeye devam etmek için lütfen yeniden bağlanın.',

    // BC-29 Communication R3 (#7688) — sınıflandırma taksonomisi (varsayılanlar).
    'category_prospect' => 'Potansiyel müşteri',
    'category_client' => 'Müşteri',
    'category_supplier' => 'Tedarikçi',
    'category_invoice' => 'Fatura',
    'category_commercial' => 'Ticari',
    'category_hr' => 'İK',
    'category_spam_newsletter' => 'Spam / bülten',
    'category_personal' => 'Kişisel',
    'category_urgent' => 'Acil',
    'category_other' => 'Diğer',

    // BC-29 Communication R3 (#7688) — API mesajları.
    'category_key_taken' => 'Bu kategori anahtarı şirketiniz için zaten mevcut.',
    'proposal_already_decided' => 'Bu kişi önerisi zaten karara bağlandı.',

    // BC-29 Communication R4 (#7689) — takip API mesajları.
    'follow_up_send_scope_required' => 'Otomatik takipleri etkinleştirmek için Gmail posta kutunuzu gönderme izniyle yeniden bağlayın.',
    'follow_up_not_cancellable' => 'Bu takip zaten işlendi ve artık iptal edilemez.',
    'follow_up_mailbox_not_found' => 'Posta kutusu bulunamadı.',

    // BC-29 Communication R5 (#7690) — destekli yanıt API mesajları.
    'reply_mailbox_not_found' => 'Posta kutusu bulunamadı.',
    'reply_category_unknown' => 'Bu kategori şirketinizin sınıflandırmasında mevcut değil.',
    'reply_auto_category_blocked' => 'Bu kategori için otomatik gönderime izin verilmiyor (finans, İK veya hukuk).',
    'reply_send_scope_required' => 'Destekli yanıtları etkinleştirmek için Gmail posta kutunuzu gönderme izniyle yeniden bağlayın.',
    'reply_compose_scope_required' => 'Gmail taslaklarını etkinleştirmek için Gmail posta kutunuzu oluşturma izniyle yeniden bağlayın.',
    'pending_reply_not_pending' => 'Bu yanıt önerisi zaten işlendi.',
    'reply_blocked' => 'Bu yanıt gönderilemez: bir koruma önlemi engelledi.',
    'reply_rate_limited' => 'Gmail şu anda gönderimleri sınırlandırıyor. Lütfen kısa süre sonra tekrar deneyin.',
    'reply_auth_failed' => 'Gmail posta kutunuzla bağlantının süresi doldu. Lütfen yeniden bağlayın.',
    'reply_send_failed' => 'Yanıt gönderilemedi. Lütfen tekrar deneyin.',
];
