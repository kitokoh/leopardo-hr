<?php

// Platform admin kokpit i18n anahtarları (SPA admin sözleşmesi, issue #1764).
return [
    'alert_redis_unreachable' => 'Redis erişilemiyor — önbellek/kuyruk bozuldu.',
    'alert_queue_depth' => 'Kuyruk birikmesi yüksek: :count iş bekliyor.',
    'alert_failed_jobs' => ':count başarısız iş — kuyruğu kontrol edin.',
    'alert_licenses_expiring' => ':count Edge lisansı 30 gün içinde sona eriyor.',
    'alert_trials_expiring' => ':count deneme 7 gün içinde sona eriyor.',
    'alert_high_priority_tickets' => ':count yüksek öncelikli destek bileti açık.',
    'activity_company_created' => 'Yeni şirket: :name',
    'activity_support_ticket' => 'Destek bileti: :subject',
    'activity_edge_sync' => 'Edge senkronizasyonu: :name',
    'activity_user_signup' => 'Yeni kullanıcı: :name (:email)',
    'admin_chat_unavailable' => 'Yapay zekâ asistanı kiracı bazında yapılandırılır: platform konsolu bir kiracı adına yanıt veremez. Asistanı kullanmak için kiracı çalışma alanına giriş yapın.',
    'conversation_not_found' => 'Conversation introuvable.',
    'conversations_unavailable' => 'Conversations indisponibles.',
    'oauth_save_failed' => 'Yapılandırma kaydedilemedi.',
    'ai_settings_unknown_keys' => 'Bilinmeyen ayar(lar): :keys',
    'ai_settings_unknown_key' => 'Bilinmeyen ayar: :key',
    'ai_test_driver_fake' => '"fake" sürücüsü: hiçbir ağ çağrısı yapılmaz. Bir anahtarı test etmek için gerçek bir sağlayıcı seçin.',
    'ai_test_ok' => 'Sağlayıcı doğru yanıt verdi.',
    'ai_test_unauthorized' => 'Anahtar sağlayıcı tarafından reddedildi (401). Bu sürücü için kayıtlı anahtarı kontrol edin.',
    'ai_test_quota' => 'Sağlayıcı kotası doldu (429). Daha sonra tekrar deneyin veya planı değiştirin.',
    'ai_test_timeout' => 'Sağlayıcıya ulaşılırken zaman aşımı. Sunucunun giden bağlantısını kontrol edin.',
    'ai_test_failed' => 'Test başarısız: :error',
];
