<?php

declare(strict_types=1);

/**
 * Catalogue Trade (BC-36) — messages du profil commercial et de sa
 * publication dans l'annuaire public B2B (#9617).
 *
 * Ces messages etaient des litteraux accentues dans le controleur : la garde
 * PA2-I18N-007 (#5432) les refuse sur les surfaces a risque i18n, et a raison —
 * un message d'erreur francais code en dur est invisible pour un utilisateur
 * turc ou arabophone.
 */
return [
    'direction_required' => 'En az bir yon belirtin: ithalat, ihracat veya yurt ici pazar.',
    'profile_required_before_publish' => 'Yayinlamadan once ticari profilinizi tamamlayin.',
    'public_slug_required' => 'Sirketi yayinlamak icin genel bir slug ve ticari ad gereklidir.',
    'public_slug_taken' => 'Bu genel slug baska bir sirket tarafindan kullaniliyor.',
    // #9777 — RFQ/teklif satirlari: secilen katalog urunu.
    'lines' => [
        'product_invalid' => 'Bu urun kataloğunuzda yok veya başka bir şirkete ait.',
        'product_archived' => 'Bu urun arşivlenmiş: yeni bir satıra eklenemez.',
    ],
    // #9779 — karsilastrilar (musteri/tedarikci dizini).
    'counterparties' => [
        'roles_required' => 'En az bir rol belirtin: yonu bilmeden bir tarafla alim-satim yapilamaz.',
        'verification_not_self_assertable' => 'Dogrulama ekibinizden bir uye tarafindan yapilir, burada beyan edilemez.',
    ],

    // #9779 — siparisler.
    'orders' => [
        'cancel_reason_required' => 'Iptal gerekcesini belirtin: olmadan siparis sonradan aciklanamaz.',
    ],

    // #9779 — ticari belgeler.
    'documents' => [
        'type_invalid' => 'Bu belge tipi duzenleyici listede yer almiyor.',
        'title_required' => 'Belgeye bir baslik verin.',
        'link_required' => 'Belgeyi bir siparise, teklife, talebe veya isleme baglayin.',
    ],
    'search_query_too_short' => 'Arama en az :min karakter icermelidir.',
];
