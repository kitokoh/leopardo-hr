<?php

/**
 * BC-39 PAGES — sunucu mesajlari (tr).
 *
 * Anahtar paritesi fr/en/ar ile birebir olmalidir (check-i18n-catalog-parity.sh).
 */
return [
    // #8723 — yayinlama ve isletmeye baglama
    'publish_seo_title_required' => 'SEO basligi olmayan bir Sayfa yayinlanamaz: basliksiz, dizine eklenebilir bir URL uretirdi.',
    'site_not_found' => 'Sayfa icin isletme bulunamadi (site_id=:site).',
    'site_cross_tenant' => ':site isletmesi :company sirketine ait degil: bir Sayfa baska bir kiracinin konumuna baglanamaz.',

    // #8724 — blok kaydi
    'block_type_duplicate' => '«:type» blok turu zaten «:owner» baglami tarafindan saglaniyor: «:new» onu yeniden kaydedemez.',

    // #8725 — dogrulama dosyasi
    'verification_method_disabled' => '«:method» dogrulama yontemi bu platformda etkin degil.',
    'verification_already_open' => 'Bu Sayfa icin zaten acik bir dogrulama dosyasi var.',
    'verification_closed' => 'Bu dogsrulama dosyasi kapali: yeni bir dogrulama istemek icin yeni dosya acin.',
    'verification_suspended' => 'Askida olan bir Sayfa dogrulama talebinde bulunamaz.',
    'verification_wrong_method' => 'Bu islem «:expected» yontemini gerektirir (dosya «:actual» durumunda).',
    'verification_cooldown' => 'Az once bir kod gonderildi: :seconds saniye sonra tekrar deneyin.',
    'verification_code_not_found' => 'Bu dosyada bu numara icin kod uretilmedi.',
    'verification_code_expired' => 'Bu kodun suresi doldu: yeni bir kod isteyin.',
    'verification_code_consumed' => 'Bu kod zaten kullanildi.',
    'verification_code_locked' => 'Cok fazla deneme: yeni bir kod isteyin.',
    'verification_code_mismatch' => 'Hatali kod (:remaining deneme hakki kaldi).',
    'verification_no_evidence' => 'Belgesel bir dosya hicbir destekleyici belge olmadan onaylanamaz.',
    'verification_evidence_invalid' => 'Destekleyici belge reddedildi: :errors',
    'verification_evidence_path_public' => 'Bir belgenin depolama yolu genel bir URL veya mutlak bir yol olamaz.',
    'verification_reason_invalid' => 'Bilinmeyen karar gerekcesi: «:reason».',
    'verification_transition_invalid' => 'Dogrulama gecisi mumkun degil: :from -> :to.',

    // #8726 — ozel alan adlari
    'domain_invalid' => 'Bu alan adi gecerli degil: :reason',
    'domain_taken' => 'Bu alan adi zaten bir Sayfaya bagli. Sahibiyseniz destek ile iletisime gecin.',
    'domain_already_linked' => 'Bu alan adi zaten bu Sayfaya bagli.',
    'domain_entitlement_missing' => 'Ozel alan adlari mevcut planiniza dahil degil.',
    'domain_quota_exceeded' => 'Planiniz :limit ozel alan adina izin veriyor.',
    'domain_removed' => 'Bu alan adi kaldirildi ve artik dogrulanamaz.',
    'domain_plan_expired' => 'Aboneligiiz sona erdi: ozel alan adlari yenilenene kadar devre disi.',

    // #8728 - kullanilamaz veya ayrilmis Sayfa adresi
    'slug_unusable' => 'Verilen ad herkese acik bir adres olusturmaya uygun degil. Harf ve rakam kullanin.',
    'slug_reserved' => '":slug" adresi platform tarafindan ayrilmis. Baska bir ad secin.',
    'slug_taken' => '":slug" adresi zaten kullaniliyor.',

    // #8730 — public SSR rendering
    'public' => [
        'verified' => 'Dogrulanmis isletme',
        'contact' => 'Iletisim',
        'map' => 'Haritada gor',
        'actions' => [
            'directions' => 'Yol tarifi',
            'contact' => 'Yaz',
        ],
    ],
];
