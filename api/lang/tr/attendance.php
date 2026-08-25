<?php

return [
    // Statuts
    'status_present' => 'Mevcut',
    'status_absent' => 'Devamsız',
    'status_late' => 'Geç',
    'status_leave' => 'İzinli',
    'status_ontime' => 'Zamanında',
    'status_incomplete' => 'Tamamlanmadı',

    // Actions
    'check_in' => 'Giriş kaydı',
    'check_out' => 'Çıkış kaydı',
    'check_in_success' => 'Giriş kaydı başarılı.',
    'check_out_success' => 'Çıkış kaydı başarılı.',

    // Labels
    'hours_worked' => ':hours saat çalışıldı',
    'late_by_minutes' => ':minutes dakika geç',
    'check_in_time' => 'Giriş: :time',
    'check_out_time' => 'Çıkış: :time',
    'no_data_today' => 'Bugün kayıt yok',
    'overtime' => 'Fazla mesai',
    'daily_summary' => 'Günlük özet',
    'monthly_summary' => 'Aylık özet',
    'history' => 'Geçmiş',

    // Konum oturumları (Attendance)
    'geo_session_approved' => 'Oturum onaylandi. Devam kaydi olusturuldu.',
    'geo_session_rejected' => 'Oturum reddedildi.',

    // Devam duzeltmeleri (yonetici/IK incelemesi)
    'corrections_title' => 'Devam duzeltmeleri',
    'corrections_subtitle' => 'Calisanlar tarafindan gonderilen duzeltme taleplerini onaylayin veya reddedin.',
    'corrections_empty' => 'Su anda duzeltme talebi yok.',
    'correction_reason_label' => 'Calisan gerekcesi',
    'correction_requested_check_in' => 'Talep edilen giris',
    'correction_requested_check_out' => 'Talep edilen cikis',
    'correction_status_pending' => 'Beklemede',
    'correction_status_applied' => 'Uygulandi',
    'correction_status_rejected' => 'Reddedildi',
    'correction_approve' => 'Onayla',
    'correction_reject' => 'Reddet',
    'correction_applied' => 'Duzeltme devam kaydina uygulandi.',
    'correction_rejected' => 'Duzeltme reddedildi.',
    'correction_already_processed' => 'Bu duzeltme talebi zaten islendi.',
    'correction_filter_pending' => 'Beklemede',
    'correction_filter_applied' => 'Uygulandi',
    'correction_filter_rejected' => 'Reddedildi',
    'correction_filter_all' => 'Tumu',

    'correction_transmitted' => 'Degisiklik talebi IK\'ya iletildi.',

    // #4311 — validations correction/pointage localisées
    'correction_future_check_in' => 'Gelecekteki bir saat için düzeltme talep edemezsiniz.',
    'correction_future_check_out' => 'Gelecekteki bir saat için düzeltme talep edemezsiniz.',
    'correction_already_processed' => 'Bu düzeltme talebi zaten işlendi.',
    'manual_checkout_requires_check_in' => 'Manuel çıkış için giriş saati gerekir.',
    'checkout_after_checkin' => 'Çıkış saati giriş saatinden sonra olmalıdır.',

    // Issue #5269 — messages API pointage (zéro chaîne hardcodée utilisateur).
    'workflow_deactivated' => 'İş akışı devre dışı bırakıldı.',
    'request_not_pending' => 'Talep beklemede değil.',
    'calendar_disconnected' => 'Takvim bağlantısı kesildi.',
    'geo_event_no_session' => 'Olay işlendi (çıkış için açık oturum bulunamadı).',
    'geo_event_processed' => 'Coğrafi olay başarıyla işlendi.',
];
