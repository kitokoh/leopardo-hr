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
];
