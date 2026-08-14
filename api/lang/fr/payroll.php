<?php

return [
    'zero_slips_generated' => 'Aucun bulletin généré : vérifiez qu\'au moins une structure salariale active existe pour ce pays avant de calculer la paie.',
    'public_holidays_admin_only' => 'Seul un super-admin ou un manager principal peut gérer les jours fériés.',
    'public_holidays_company_only' => 'Un manager principal ne peut modifier que les jours fériés de sa propre entreprise.',
    'rate_edit_locked' => "Une ligne soumise, active ou remplacée ne peut plus être modifiée — proposez une nouvelle modification.",
    'rate_delete_draft_only' => "Seule une ligne en brouillon peut être supprimée.",
    'rate_country_unsupported' => "Pays non supporté.",
    'tax_scale_default_name' => ":country barème légal :year",

    // Issue #1923 — workflow de validation des taux légaux (#1813) : messages
    // du service/listener/contrôleurs admin, plus aucune chaîne accentuée en dur.
    'rate_submit_draft_only' => 'Seule une ligne en brouillon peut être soumise (statut actuel : :status).',
    'rate_approve_pending_only' => 'Seule une ligne en attente de validation peut être approuvée (statut actuel : :status).',
    'rate_reject_pending_only' => 'Seule une ligne en attente de validation peut être rejetée (statut actuel : :status).',
    'rate_reject_reason_required' => 'Un motif de rejet est obligatoire.',
    'rate_table_unknown' => 'Table inconnue.',
    'rate_overlap_conflict' => 'Une ligne active existe déjà pour cette même identité sur une période qui chevauche la nouvelle fenêtre d\'effet : fermez d\'abord la fenêtre de la ligne existante.',
    'rate_validation_requested_title' => 'Validation de taux demandée — :label',
    'rate_validation_requested_body' => 'Un :kind de taux légal (:label) attend votre validation dans l\'interface admin.',
    'rate_kind_tax_scale' => 'barème fiscal',
    'rate_kind_contribution' => 'taux de cotisation',
    'rate_approved_title' => 'Modification de taux approuvée',
    'rate_approved_body' => 'Votre modification de taux légal (:label) a été approuvée et est active.',
    'rate_rejected_title' => 'Modification de taux rejetée',
    'rate_rejected_body' => 'Votre modification de taux légal (:label) a été rejetée : :reason',

    'placeholder_acknowledge_required' => "Les règles de paie pour le pays :country sont encore au stade « placeholder » : aucune valeur légale n'est implémentée. Confirmez explicitement (acknowledge_placeholder=true) — les montants sont INDICATIFS et ne peuvent pas servir à un bulletin réel.",
    'compliance_warning_placeholder' => "Règles de structure uniquement pour :country : les taux et cotisations ne sont pas encore sourcés — ne pas utiliser pour une paie réelle.",
    'compliance_warning_pilot' => "Règles pilotes pour :country, issues de références publiques mais non validées localement — confirmez avec un conseil local avant tout usage réglementaire.",
    'compliance_warning_production' => "Règles validées pour la paie :country — confirmez toujours les taux courants auprès d'un conseil local avant une déclaration.",
];
