<?php

declare(strict_types=1);

// BC-32 HOSPITALITY (HOSP-001..008, EPIC #7951) — HospitalityManager dikeyi
// kullanıcı mesajları (PA2-I18N-007: katalog dışı metin yok).
return [
    'solution_inactive' => 'HospitalityManager çözümü bu kiracı için aktif değil.',
    'room_type_unavailable' => 'Bu oda tipi bu tesis için uygun değil.',
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
    'invalid_lease_transition' => 'Geçersiz kira sözleşmesi geçişi (:from → :target).',
    'lease_terminal_immutable' => 'Sonlanmış bir sözleşme artık değiştirilemez: koşulları düzenlenmiş taksitlerle sabitlenmiştir.',
    'rent_already_settled' => 'Bu taksit zaten ödendi: ek tahsilat yapılamaz.',
    'rent_amount_must_be_positive' => 'Tahsil edilen tutar sıfırdan büyük olmalıdır.',
    'rent_amount_exceeds_outstanding' => 'Tahsil edilen tutar (:amount) kalan borcu (:outstanding) aşıyor.',
];
