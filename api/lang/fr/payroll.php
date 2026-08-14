<?php

return [
    'zero_slips_generated' => 'Aucun bulletin généré : vérifiez qu\'au moins une structure salariale active existe pour ce pays avant de calculer la paie.',
    'public_holidays_admin_only' => 'Seul un super-admin ou un manager principal peut gérer les jours fériés.',
    'public_holidays_company_only' => 'Un manager principal ne peut modifier que les jours fériés de sa propre entreprise.',
    'rate_edit_locked' => "Une ligne soumise, active ou remplacée ne peut plus être modifiée — proposez une nouvelle modification.",
    'rate_delete_draft_only' => "Seule une ligne en brouillon peut être supprimée.",
    'rate_country_unsupported' => "Pays non supporté.",
    'tax_scale_default_name' => ":country barème légal :year",
    // Issue #1872 — niveau de confiance des règles pays : messages localisés
    // consommés via Lang::get('payroll.confidence.*') à la frontière API
    // (presenter, simulation, registre des pays).
    'confidence' => [
        'label' => 'Confiance des règles de paie',
        'production' => [
            'message' => 'Règles validées et utilisées en production pour :country. Confirmez toujours les taux en vigueur auprès d\'un conseil local avant de vous appuyer sur ces montants pour des déclarations obligatoires.',
        ],
        'pilot' => [
            'message' => 'Règles pilotes pour :country : montants issus de références publiques générales (code du travail) mais non encore validés juridiquement sur place. Confirmez avec un conseil juridique ou fiscal local avant de vous appuyer sur ces chiffres (tranches d\'impôt, cotisations sociales, seuils d\'heures supplémentaires) pour vos obligations légales.',
        ],
        'placeholder' => [
            'message' => 'Maquette sans valeurs pour :country : les montants d\'impôt et de cotisations sociales ne sont pas encore documentés et ne doivent pas être utilisés pour de vrais cycles de paie tant qu\'ils n\'ont pas été remplacés.',
        ],
        'unknown' => [
            'message' => 'Aucune règle de paie n\'est disponible pour :country : le calcul de paie n\'est pas disponible pour ce pays.',
        ],
    ],
];
