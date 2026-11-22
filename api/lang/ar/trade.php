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
    // #9777 — أسطر طلبات عروض الأسعار/العروض: منتج الكتالوج المحدد.
    'lines' => [
        'product_invalid' => 'هذا المنتج غير موجود في كتالوجك أو ينتمي إلى شركة أخرى.',
        'product_archived' => 'هذا المنتج مؤرشف ولا يمكن إضافته إلى سطر جديد.',
    ],
    // #9779 — الأطراف التجارية (دليل العملاء والموردين).
    'counterparties' => [
        'roles_required' => 'حدّد دوراً واحداً على الأقل: لا يمكنك البيع أو الشراء من طرف دون معرفة الاتجاه.',
        'verification_not_self_assertable' => 'التحقق يقوم به عضو من فريقك، ولا يمكن الإعلان عنه هنا.',
    ],

    // #9779 — الطلبات.
    'orders' => [
        'cancel_reason_required' => 'اذكر سبب الإلغاء: بدونه يصعب تفسير الطلب لاحقاً.',
    ],

    // #9779 — المستندات التجارية.
    'documents' => [
        'type_invalid' => 'نوع المستند هذا ليس ضمن القائمة التنظيمية.',
        'title_required' => 'أعطِ المستند عنواناً.',
        'link_required' => 'أرفق المستند بطلب أو عرض أو طلب أسعار أو صفقة.',
    ],
];
