<?php

declare(strict_types=1);

/**
 * EDU MANAGER — libellés du référentiel amorcé à l'activation (fr).
 *
 * Parité stricte des clés avec en/ar/tr (garde check-i18n-catalog-parity.sh).
 * Ces libellés sont écrits en base par `SeedMinimalEduManagerAction` : ils
 * passent par `__()` pour ne pas figer du français dans le code (PA2-I18N-007).
 */
return [
    'subject_math' => 'Mathématiques',
    'subject_lang' => 'Langue principale',
    'subject_sci' => 'Sciences',
    'subject_hist' => 'Histoire-Géographie',
    'subject_pe' => 'Éducation physique',
    'subject_art' => 'Arts',
    'fee_type_tuition' => 'Scolarité',
    'fee_type_canteen' => 'Cantine',
    'fee_type_transport' => 'Transport',
    'fee_type_registration' => 'Frais d\'inscription',
];
