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
];
