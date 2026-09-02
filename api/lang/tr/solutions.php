<?php

declare(strict_types=1);

/**
 * Sunum çözümleri etiketleri (PDF çıktısı) — Türkçe.
 * api/lang/fr/solutions.php ile aynı anahtarlar (eşleşme zorunlu).
 */

return [
    'restaurant' => [
        'package' => [
            'mobile_employee' => 'Çalışan mobil uygulaması',
            'mobile_manager' => 'Yönetici mobil uygulaması',
            'attendance_mobile' => 'Konumlu mobil yoklama',
            'kiosk' => 'Yoklama kiosku',
            'edge' => 'Yerel Edge düğümü (çevrimdışı)',
            'planning' => 'Ekip planlaması',
            'payroll' => 'Maaş bordrosu (çok ülkeli)',
            'accounting' => 'Muhasebe',
            'delivery' => 'Teslimat yönetimi',
            'reservations' => 'Çevrimiçi rezervasyonlar',
            'inventory' => 'Stok yönetimi',
            'loyalty' => 'Sadakat ve pazarlama',
            'pos' => 'Bağlı yazar kasa (POS)',
        ],
        'reason' => [
            'base' => 'Temel: çalışanlarınız yoklama alır ve bordrolarını görür.',
            'manager' => 'Ekipleriniz mobil cihazdan yönetmek için yeterince büyük.',
            'attendance_mobile' => 'Mobil yoklamayı seçtiniz.',
            'kiosk' => 'Kiosk yoklamasını seçtiniz.',
            'edge' => 'Kiosk, yerel düğüm sayesinde internetsiz de çalışır.',
            'scheduling' => 'Ekip planlaması yapıyorsunuz.',
            'payroll' => 'Bordroyu şirket içinde yönetmek istiyorsunuz.',
            'accounting' => 'Muhasebe takibi istediniz.',
            'delivery' => 'Paket teslimat yapıyorsunuz.',
            'reservations' => 'Rezervasyon alıyorsunuz.',
            'inventory' => 'Stok takibi istiyorsunuz.',
            'loyalty' => 'Müşterilerinizi sadakatle bağlamak istiyorsunuz.',
            'pos' => 'Bağlı yazar kasa istiyorsunuz.',
        ],
    ],
    'pdf' => [
        'title' => 'Leopardo paketiniz',
        'empty' => 'Seçili öğe yok.',
        'next_steps' => 'Sonraki adımlar',
        'next_step_account' => 'Leopardo çalışma alanınızı oluşturun (ücretsiz deneme, kredi kartı gerekmez).',
        'next_step_install' => 'Mobil uygulamaları kurun (indirme sayfasındaki QR kodları).',
        'next_step_edge' => 'Yoklama kioskunu seçtiyseniz yerel Edge düğümünü kurun.',
        'footer' => 'Otomatik oluşturuldu — leopardo-hr. Paketinizi istediğiniz zaman değiştirebilirsiniz.',
    ],
];
