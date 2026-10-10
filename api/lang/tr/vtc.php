<?php

declare(strict_types=1);

// BC-34 VTC (VTC-02..06, #8358-#8362) — VTC/taksi dikey modülü mesajları
// (PA2-I18N-007: sabit metin yok, her şey bu katalogdan geçer).
return [
    'ride_creation_failed' => ':attempts denemeden sonra VTC yolculuğu oluşturulamadı.',
    'availability_locked' => ':status durumundayken müsaitlik değiştirilemez (yolculuğu tamamlayın veya operatöre başvurun).',
    'purge_positions_description' => 'Saklama süresini aşan VTC sürücü konumlarının GDPR temizliği (idempotent, denetimli).',
    'purge_positions_done_log' => 'vtc:purge-positions — sürücü konumlarının GDPR temizliği yürütüldü.',
    'purge_positions_done_cli' => 'VTC temizliği tamamlandı: :deleted konum silindi (saklama :days g, eşik :cutoff).',
    'driver_delete_has_rides' => 'Yolculuğu olan sürücü: silme mümkün değil (askıya almayı tercih edin — geçmiş korunur).',
    'driver_user_not_found' => 'Çalışan hesabı bu kiracıda bulunamadı.',
    'driver_vehicle_not_found' => 'Araç bu kiracıda bulunamadı.',
    'fare_profile_delete_in_use' => 'Yolculuklarla ilişkili tarife profili: silme mümkün değil (yolculuklar geçmiş fiyat teklifini korur).',
    'vehicle_delete_assigned' => 'Araç hâlâ bir sürücüye atanmış: silmeden önce atamayı kaldırın.',
    'vehicle_plate_taken' => ':plate plakası bu kiracı için zaten kayıtlı.',
];
