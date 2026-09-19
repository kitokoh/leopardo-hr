<?php

return [
    'order' => [
        'quantity_positive' => 'Miktar kesinlikle pozitif olmalıdır.',
    ],
    'notifications' => [
        'order_ready_title' => 'Sipariş hazır',
        'order_ready_body' => 'Sipariş %s servise hazır (masa %s).',
    ],
    'reservation' => [
        'deposit_exists' => 'Bu rezervasyon için zaten bir depozito var.',
        'deposit_on_terminated' => 'Sonlandırılmış bir rezervasyon için depozito kaydedilemez.',
    ],
    'loyalty' => [
        'opt_in_required' => 'Sadakat programını etkinleştirmek için KVKK onayı gereklidir.',
    ],
    'commands' => [
        'outbox_description' => 'Vadesi gelen RestaurantManager outbox olaylarını tüketir (idempotent, geri alımlı).',
        'stock_alert_description' => 'RestaurantManager stok eşiği uyarılarını yayınlar (günde bir kez).',
        'reservation_jobs_description' => 'Rezervasyon hatırlatmaları ve gelmeme (idempotent).',
        'stock_alert_scan_result' => '%s (%s): %d uyarı oluşturuldu, %d yinelenen yok sayıldı.',
        'stock_alert_total' => 'Toplam: %d uyarı oluşturuldu, %d yinelenen.',
    ],
    // RESTO-805 (#6226) / RESTO-902 (#7747) — herkese açık çevrimiçi sipariş.
    'public_shop' => [
        'product_unavailable' => 'Bu ürün çevrimiçi sipariş için mevcut değil.',
        'product_not_served' => 'Bu ürün bu işletmede servis edilmiyor.',
        'currency_mismatch' => 'Ürün para birimi sipariş para birimiyle eşleşmiyor.',
        'quantity_invalid' => 'Miktar kesinlikle pozitif olmalıdır.',
        'empty_order' => 'Sepet boş.',
    ],
    // RESTO-902 (#7747) — herkese açık müşteri yorumları.
    'public_reviews' => [
        'order_not_eligible' => 'Yorum yalnızca servis edilmiş veya teslim edilmiş bir sipariş için yapılabilir.',
        'already_reviewed' => 'Bu sipariş için zaten bir yorum gönderildi.',
    ],
];
