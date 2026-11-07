<?php

return [
    'demo_link_invalid' => 'Geçersiz veya daha önce kullanılmış demo erişim bağlantısı.',
    'demo_link_expired' => 'Bu demo erişim bağlantısının süresi doldu. Yeni erişim isteyin.',
    'login_success' => 'Giriş başarılı.',
    'logout_success' => 'Çıkış başarılı.',
    'session_expired' => 'Oturumunuz sona erdi. Lütfen tekrar giriş yapın.',
    'account_suspended' => 'Hesabınız askıya alındı. Yöneticinizle iletişime geçin.',
    'account_locked' => 'Hesap geçici olarak kilitlendi. Lütfen daha sonra tekrar deneyin.',
    'welcome' => 'Hoş geldiniz :name!',
    'password_changed' => 'Şifre başarıyla değiştirildi.',
    'password_reset_sent' => 'Bu e-posta icin bir hesap varsa, bir sifirlama baglantisi gonderildi.',
    'failed' => 'Bu kimlik bilgileri kayıtlarımızla eşleşmiyor.',
    'throttle' => 'Çok fazla giriş denemesi. Lütfen :seconds saniye sonra tekrar deneyin.',

    'invalid_reset_token' => 'Gecersiz, suresi dolmus veya zaten kullanilmis sifirlama jetonu.',
    'password_reset_done' => 'Sifre sifirlandi. Yeni sifrenizle giris yapin.',
    'twofa_code_required' => 'Bu hesap icin 2FA kodu gereklidir.',
    'twofa_code_invalid' => '2FA kodu gecersiz veya suresi dolmus.',
    'email_already_used' => 'Bu e-posta adresi zaten kullaniliyor.',
    'current_password_incorrect' => 'Mevcut sifre hatali.',
    'twofa_already_enabled' => 'Bu hesap icin 2FA zaten etkin.',
    'twofa_not_setup' => 'Onceki adimda setup2fa cagirarak bir secret olusturun.',
    'twofa_code_invalid_value' => 'Saglanan 2FA kodu gecersiz.',
    'password_incorrect' => 'Hatali sifre.',
    'login_code_sent' => 'Bu e-posta için uygun bir hesap varsa bir giriş kodu gönderildi.',
    'login_code_invalid' => 'Giriş kodu geçersiz veya süresi dolmuş. Yeni bir kod isteyin.',
    'login_code_too_many_attempts' => 'Çok fazla deneme. Yeni bir giriş kodu isteyin.',
    'login_code_password_required' => 'Bu hesap parola ile giriş gerektirir.',
    'login_code_send_failed' => 'Giriş kodu gönderilemedi. Lütfen kısa süre sonra tekrar deneyin.',

    // COULOIR 16 — M2 (#9104) · Telefonla OTP (genel kanal).
    'public_phone_otp_invalid' => 'Geçersiz giriş kodu. SMS ile aldığınız kodu kontrol edin.',
    'public_phone_otp_expired' => 'Bu kodun süresi doldu. Yeni bir kod isteyin.',
    'public_phone_otp_too_many_attempts' => 'Çok fazla deneme. Yeni kod istemeden önce bekleyin.',
    'public_account_deletion_confirmation_required' => 'Hesabınızı silmek için onay kelimesini girin.',
];
