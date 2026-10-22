<?php

declare(strict_types=1);

// BC-37 AGRICULTURE — Farm OS (#8549-#8570). Modül i18n kataloğu:
// PA2-I18N-007 / issue #5432 — arka uçta sabit kodlanmış kullanıcı metni yok,
// her mesaj __('farm.xxx') üzerinden geçer.
return [
    // Adım 4 (hazırlama) kontrol listesi — `done` durumu veritabanından okunur,
    // burada yalnızca etiket çevrilir.
    'checklist' => [
        'activity_declared' => 'Tarımsal faaliyet bildirildi',
        'tools_decided' => 'Etkinleştirilen araçlar',
        'solution_active' => 'Farm OS çözümü etkin',
        'first_site' => 'İlk işletme / saha oluşturuldu',
        'first_parcel' => 'İlk parsel oluşturuldu',
        'first_operation' => 'İlk operasyon planlandı',
        'workers_ready' => 'Çalışanlar aracı etkinleştirildi',
    ],

    // T1.3 — operasyon atama bildirimi (BC-13 COMMS).
    'notifications' => [
        'operation_assigned_title' => 'Yeni operasyon atandı',
        'operation_assigned_body' => '":title" operasyonu size atandı.',
    ],
];
