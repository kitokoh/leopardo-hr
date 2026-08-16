<?php

return [
    // Auth
    'INVALID_CREDENTIALS' => 'E-posta veya şifre hatalı.',
    'ACCOUNT_SUSPENDED' => 'Hesabınız askıya alındı. Yöneticinizle iletişime geçin.',
    'ACCOUNT_ARCHIVED' => 'Bu hesap arşivlenmiş.',
    'TOKEN_EXPIRED' => 'Oturumunuz sona erdi. Lütfen tekrar giriş yapın.',
    'TOO_MANY_ATTEMPTS' => 'Çok fazla deneme. :minutes dakika sonra tekrar deneyin.',
    'EMPLOYEE_NOT_ACTIVE' => 'Bu çalışan hesabı aktif değil.',
    'COMPANY_NOT_FOUND' => 'Şirket bulunamadı.',
    'INVALID_CURRENT_PASSWORD' => 'Mevcut şifre hatalı.',
    'UNAUTHENTICATED' => 'Giriş yapmanız gerekiyor.',

    // Pointage
    'ALREADY_CHECKED_IN' => 'Bugün zaten giriş kaydınızı yaptınız.',
    'MISSING_CHECK_IN' => 'Çıkış yapmadan önce giriş kaydı yapmalısınız.',
    'ALREADY_CHECKED_OUT' => 'Bugün zaten çıkış kaydınızı yaptınız.',
    'PUNCH_PHOTO_REQUIRED' => 'Şirketinizde giriş/çıkış için fotoğraf zorunludur.',

    // Finance
    'PLAN_CAMERAS_REQUIRED' => 'Planınız kamera modülünü içermiyor. Business planına yükseltin.',
    'MAX_CAMERAS_REACHED' => 'Planınız için :limit kamera sınırına ulaşıldı.',
    'PLAN_FINANCE_REQUIRED' => 'Planınız finans modülünü içermiyor.',
    'FINANCE_MAX_DOCS_REACHED' => 'Bu ay için :limit belge sınırına ulaşıldı.',
    'INVOICE_ALREADY_SENT' => 'Bu fatura zaten gönderildi ve değiştirilemez.',

    // Invitations
    'INVITATION_ALREADY_ACCEPTED' => 'Bu davet zaten kabul edildi.',
    'INVITATION_EXPIRED' => 'Bu davetin süresi doldu.',
    'INVITATION_NOT_FOUND' => 'Davet bulunamadı.',

    // Biometric
    'CAMERA_TOKEN_EXPIRED' => 'Bu kameraya erişim süresi doldu.',
    'CAMERA_TOKEN_REVOKED' => 'Bu erişim iptal edildi.',

    // Payroll
    'PAYROLL_BALANCE_UNAVAILABLE' => 'Çalışan bakiyesi geçici olarak kullanılamıyor. Lütfen biraz sonra tekrar deneyin.',
    // Général
    'NOT_FOUND' => 'Kaynak bulunamadı.',
    'FORBIDDEN' => 'Bu işlem için yetkiniz yok.',
    'SERVER_ERROR' => 'Bir hata oluştu. Lütfen tekrar deneyin.',
    'VALIDATION_ERROR' => 'Bazı alanlar hatalı.',
    'BAD_REQUEST' => 'Gecersiz istek.',
    'CONFLICT' => 'Veri cakismasi bu islemi engelliyor.',
    'VALIDATION_FAILED' => 'Bazi alanlar hatali.',
    'TOO_MANY_REQUESTS' => 'Cok fazla istek. Lutfen daha sonra tekrar deneyin.',
    'SERVICE_UNAVAILABLE' => 'Hizmet gecici olarak kullanilamiyor.',
    'HTTP_ERROR' => 'Bir hata olustu. Lutfen tekrar deneyin.',
    'UNSUPPORTED_API_VERSION' => 'Desteklenmeyen API surumu.',

    'PAYMENT_SESSION_FAILED' => 'Ödeme oturumu oluşturulamadı.',
    'NO_PAYMENT_ACCOUNT' => 'İlişkili ödeme hesabı yok. Önce bir plana abone olun.',
    'VERIFICATION_CODE_SENT' => 'Doğrulama kodu gönderildi.',
    'VERIFICATION_TEMPORARILY_UNAVAILABLE' => 'İsteğinizin doğrulaması geçici olarak kullanılamıyor. Lütfen kısa süre sonra tekrar deneyin.',
    'TRIAL_SPACE_READY' => 'Leopardo çalışma alanınız hazır!',
    'SESSION_ALREADY_OPEN' => 'Bu çalışan için zaten açık bir oturum var.',
    'OUTSIDE_GEOFENCE' => 'Konum, yoklama bölgesinin dışında.',
    'ATTENDANCE_MODE_PERSONALIZATION_DISABLED' => 'Yoklama modu kişiselleştirmesi devre dışı.',
    'PREFERENCE_UPDATED' => 'Tercih güncellendi.',
    'CONFIG_UPDATED' => 'Yapılandırma güncellendi.',
    'PAYROLL_RUN_LOCKED' => 'Bu maaş çalışması kilitli (muhasebe kapanışı) ve artık değiştirilemez.',
    'PAYROLL_RUN_NOT_LOCKED' => 'Yalnızca kilitli bir çalışma düzenlenebilir.',
    'PAYOUT_REQUEST_REFUSED' => 'Ödeme talebi reddedildi.',
    'PAYOUT_REQUEST_FAILED' => 'Ödeme talebi sırasında bir hata oluştu.',
    'COMPANY_MODE_FORCED' => 'Şirketiniz bir mesai modu dayatıyor. Değiştiremezsiniz.',
    'GPS_CONSENT_REQUIRED' => 'Otomatik mesai için GPS onayı zorunludur.',
    'PAYMENT_BATCH_RUN_INVALID' => 'Ödeme grubu oluşturmadan önce maaş döngüsü hesaplanmalı veya onaylanmalıdır.',
    'PAYMENT_BATCH_CREATED' => 'Toplu ödeme bildirildi. Çalışan onayları ve belgeler arka planda işleniyor.',
    'ARCHIVED_DOCUMENT_NOT_FOUND' => 'Arşivlenen belge bulunamadı.',
    'TOO_MANY_PENDING_REQUESTS' => 'Zaten 3 bekleyen talebiniz var.',
    'SAML_RESPONSE_MISSING' => 'SAMLResponse eksik.',
    'SAML_ASSERTION_RECEIVED' => 'SAML onayı alındı.',
    'OIDC_CODE_MISSING' => 'Kod veya id_token eksik.',
    'OIDC_LOGIN_SUCCESS' => 'OIDC girişi başarılı.',
    'TENANT_COUNTRY_REQUIRED' => 'Kiracının yasal ülkesi zorunludur ve bu işlemden önce desteklenmelidir.',
    'TENANT_COUNTRY_INVALID' => 'Kiracı ülkesi eksik veya desteklenmiyor (:country).',
    'FILE_TOO_LARGE' => 'Yüklenen dosya izin verilen maksimum boyutu aşıyor.',
    'CONTRACT_ACTIVATION_INVALID_STATE' => 'Yalnızca taslak sözleşmeler etkinleştirilebilir.',
    'CONTRACT_SUSPENSION_INVALID_STATE' => 'Yalnızca aktif sözleşmeler askıya alınabilir.',
    'CONTRACT_TERMINATION_INVALID_STATE' => 'Sözleşmenin feshedilmesi için aktif veya askıda olması gerekir.',
];
