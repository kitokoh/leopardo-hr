<?php

return [
    'zero_slips_generated' => 'Aucun bulletin généré : vérifiez qu\'au moins une structure salariale active existe pour ce pays avant de calculer la paie.',
    'public_holidays_admin_only' => 'Seul un super-admin ou un manager principal peut gérer les jours fériés.',
    'public_holidays_company_only' => 'Un manager principal ne peut modifier que les jours fériés de sa propre entreprise.',
    'rate_edit_locked' => "Une ligne soumise, active ou remplacée ne peut plus être modifiée — proposez une nouvelle modification.",
    'rate_delete_draft_only' => "Seule une ligne en brouillon peut être supprimée.",
    'rate_country_unsupported' => "Pays non supporté.",
    'tax_scale_default_name' => ":country barème légal :year",
    'compliance_warning_production' => "Règles juridiquement validées pour la paie :country, mais confirmez toujours les taux en vigueur auprès d’un conseil local avant de les utiliser pour des déclarations légales.",
    'compliance_warning_pilot' => "Jeu de règles pilote pour :country, issu de références publiques du droit du travail mais non encore validé juridiquement sur place. Confirmez avec un conseil juridique/fiscal local avant de vous appuyer sur ces chiffres (tranches fiscales, cotisations sociales, seuils d’heures sup) pour la conformité réglementaire.",
    'compliance_warning_placeholder' => "Placeholder structurel pour :country : les montants fiscaux/sociaux ne sont pas encore documentés et ne doivent pas être utilisés pour des paies réelles avant remplacement.",
    'compliance_warning_unknown' => "Aucune règle de paie disponible pour :country pour le moment.",
];
