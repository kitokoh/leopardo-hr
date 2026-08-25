<?php

return [
    // Çalışan dosyası belge kontrol listesi (issue #5326 — gap G3, spec hr-lifecycle §5)

    // Belge türleri
    'type_contract_signed' => 'İmzalı sözleşme',
    'type_employee_file' => 'Çalışan dosyası',
    'type_career_decision' => 'Kariyer kararı',
    'type_departure_record' => 'İşten ayrılış kaydı',
    'type_notice_summary' => 'Bildirim süresi özeti',
    'type_settlement' => 'Kıdem/ibra hesabı',
    'type_certificate' => 'Çalışma belgesi',
    'type_other' => 'Diğer belge',

    // Durumlar
    'status_received' => 'Alındı',
    'status_uploaded' => 'Yüklendi',
    'status_generated' => 'Oluşturuldu',
    'status_missing' => 'Eksik',

    // Mesajlar
    'created' => 'Belge başarıyla kaydedildi.',
    'updated' => 'Belge başarıyla güncellendi.',
    'deleted' => 'Belge dosyadan kaldırıldı.',
    'not_found' => 'Belge şirketinizde bulunamadı.',
    'forbidden' => 'Çalışan dosyası belgelerini yalnızca ana yönetici veya İK yöneticisi yönetebilir.',
    'dossier_complete' => 'Dosya tamam',
    'dossier_incomplete' => 'Dosya eksik',
];
