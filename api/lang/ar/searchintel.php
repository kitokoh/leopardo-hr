<?php

declare(strict_types=1);

// GSI-12 (#8523) — messages de synchronisation des connecteurs.
// Les jetons sont des valeurs dynamiques (:detail) : le message reste
// traduisible sans concaténation côté code (PA2-I18N-007).
return [
    'unauthorized' => 'تم رفض بيانات الاعتماد من المزود (يلزم إعادة الربط)',
    'quota' => 'تم تجاوز حصة المزود',
    'malformed' => 'استجابة غير مقروءة: :detail',
    'transport' => 'خطأ في النقل: :detail',
];
