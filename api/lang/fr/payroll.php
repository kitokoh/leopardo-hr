<?php

return [
    'zero_slips_generated' => 'Aucun bulletin généré : vérifiez qu\'au moins une structure salariale active existe pour ce pays avant de calculer la paie.',
    'public_holidays_admin_only' => 'Seul un super-admin ou un manager principal peut gérer les jours fériés.',
    'public_holidays_company_only' => 'Un manager principal ne peut modifier que les jours fériés de sa propre entreprise.',
    'rate_edit_locked' => "Une ligne soumise, active ou remplacée ne peut plus être modifiée — proposez une nouvelle modification.",
    'rate_delete_draft_only' => "Seule une ligne en brouillon peut être supprimée.",
    'rate_country_unsupported' => "Pays non supporté.",
    'tax_scale_default_name' => ":country barème légal :year",
    'placeholder_acknowledge_required' => "Les règles de paie pour le pays :country sont encore au stade « placeholder » : aucune valeur légale n'est implémentée. Confirmez explicitement (acknowledge_placeholder=true) — les montants sont INDICATIFS et ne peuvent pas servir à un bulletin réel.",
    'compliance_warning_placeholder' => "Règles de structure uniquement pour :country : les taux et cotisations ne sont pas encore sourcés — ne pas utiliser pour une paie réelle.",
    'compliance_warning_pilot' => "Règles pilotes pour :country, issues de références publiques mais non validées localement — confirmez avec un conseil local avant tout usage réglementaire.",
    'compliance_warning_production' => "Règles validées pour la paie :country — confirmez toujours les taux courants auprès d'un conseil local avant une déclaration.",
];
