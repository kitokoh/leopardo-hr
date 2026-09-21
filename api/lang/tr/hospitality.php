<?php

declare(strict_types=1);

// BC-32 HOSPITALITY (HOSP-001..008, EPIC #7951) — HospitalityManager dikeyi
// kullanıcı mesajları (PA2-I18N-007: katalog dışı metin yok).
return [
    'solution_inactive' => 'HospitalityManager çözümü bu kiracı için aktif değil.',
    'no_availability' => 'Bu oda tipi için bu aralıkta müsaitlik yok.',
    'invalid_transition' => 'Geçersiz rezervasyon geçişi (:from → :target).',
    'checkout_before_checkin' => 'Çıkış tarihi giriş tarihinden sonra olmalıdır.',
    'unit_room_type_mismatch' => 'Atanan birim, rezervasyonun oda tipiyle aynı olmalıdır.',
    'console' => [
        'expire_pending_description' => 'Süresi geçen bekleyen hospitality rezervasyonlarını düşürür: iptal + envanter iadesi (HOSP-004/#7946).',
        'expire_pending_no_tenant' => 'Aktif kiracı yok — düşürülecek bir şey yok.',
        'expire_pending_tenant_summary' => 'Kiracı :company : :count hospitality rezervasyonu düşürüldü.',
        'expire_pending_total' => 'Toplam : :count hospitality rezervasyonu düşürüldü.',
    ],
];
