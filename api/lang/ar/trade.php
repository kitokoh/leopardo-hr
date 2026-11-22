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
    'direction_required' => 'حدد اتجاهاً واحداً على الأقل: استيراد أو تصدير أو السوق المحلي.',
    'profile_required_before_publish' => 'أكمل ملفك التجاري قبل نشره.',
    'public_slug_required' => 'يلزم معرف واسم تجاري عامان لنشر الشركة.',
    'public_slug_taken' => 'هذا المعرف العام مستخدم بالفعل من قبل شركة أخرى.',
    'search_query_too_short' => 'يجب أن يحتوي البحث على :min أحرف على الأقل.',
];
