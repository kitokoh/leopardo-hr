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

    // #4312/#4313/#4314 — FR résiduels localisés (vague expert20)
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

    // API responses — batch i18n v4 (#4396)
    'GOOGLE_TOKEN_INVALID' => 'Google belirteci geçersiz veya süresi dolmuş.',
    'CANNOT_DISABLE_SELF' => 'Kendi hesabınızı devre dışı bırakamazsınız.',
    'CANNOT_SUSPEND_SELF' => 'Kendi hesabınızı askıya alamazsınız.',
    'MANAGER_ONLY_LINK' => 'Bir kullanıcıyı yalnızca yönetici bağlayabilir.',
    'ORDINARY_ACCOUNT_NOT_FOUND' => 'Bu e-posta ile normal hesap bulunamadı.',
    'EMPLOYEE_NOT_FOUND_IN_COMPANY' => 'Şirketinizde çalışan bulunamadı.',
    'USER_ALREADY_LINKED' => 'Bu kullanıcı zaten şirketinize bağlı.',
    'PARTNER_APPLICATION_ALREADY_SUBMITTED' => 'Başvuru zaten gönderilmiş.',
    'CSV_HEADER_REQUIRED' => 'CSV dosyası bir başlık satırı ve en az bir veri satırı içermelidir.',
    'CSV_MISSING_COLUMNS' => 'Gerekli sütunlar eksik: :columns',
    'CSV_IMPORT_FAILED' => 'İçe aktarılırken bir hata oluştu.',
    'NO_ACTIVE_PLAN_FOR_COMPANY' => 'Bu şirketi oluşturmak için uygun aktif plan yok.',
    'EMAIL_ALREADY_USED_BY_USER' => 'Bu e-posta mevcut bir kullanıcı tarafından kullanılıyor.',
    'COMPANY_REQUEST_ALREADY_PROCESSED' => 'Bu talep zaten işlenmiş.',
    'LEAVE_REQUESTS_PENDING_VALIDATION' => 'Onayınızı bekleyen izin talepleri var.',
    'TRAININGS_PENDING_CLOSURE' => 'Bazı eğitimler tamamlandı ancak kayıtlar kapatılmadı.',
    'LOW_LEAVE_DAYS' => 'Bazı çalışanların 2 gün veya daha az izni kaldı.',
    'ONLINE_PAYMENT_NOT_CONFIGURED' => 'Çevrimiçi ödeme henüz yapılandırılmadı.',
    'BILLING_PORTAL_ACCESS_FAILED' => 'Faturalandırma portalına erişilemiyor.',
    'FLEET_ALERTS_LOAD_FAILED' => 'Filo uyarıları yüklenemedi.',
    'COMPANY_BRANDING_UPDATED' => 'Şirket kimliği güncellendi.',
    'PAYROLL_RUN_NOT_FOR_COUNTRY' => 'Bu maaş işlemi :country ile ilgili değil.',
];
