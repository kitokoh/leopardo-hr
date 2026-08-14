<?php

return [
    'zero_slips_generated' => 'Aucun bulletin généré : vérifiez qu\'au moins une structure salariale active existe pour ce pays avant de calculer la paie.',
    'public_holidays_admin_only' => 'Seul un super-admin ou un manager principal peut gérer les jours fériés.',
    'public_holidays_company_only' => 'Un manager principal ne peut modifier que les jours fériés de sa propre entreprise.',
    'rate_edit_locked' => "Une ligne soumise, active ou remplacée ne peut plus être modifiée — proposez une nouvelle modification.",
    'rate_delete_draft_only' => "Seule une ligne en brouillon peut être supprimée.",
    'rate_country_unsupported' => "Pays non supporté.",
    'tax_scale_default_name' => ":country barème légal :year",
    'rate_submit_draft_only' => "Seule une ligne en brouillon peut être soumise (statut actuel : :status).",
    'rate_approve_pending_only' => "Seule une ligne en attente de validation peut être approuvée (statut actuel : :status).",
    'rate_reject_pending_only' => "Seule une ligne en attente de validation peut être rejetée (statut actuel : :status).",
    'rate_notif_title_submitted' => "Validation de taux demandée — :label",
    'rate_notif_body_submitted' => "Un :kind de taux légal (:label) attend votre validation dans l'interface admin.",
    'rate_notif_kind_slab' => "barème fiscal",
    'rate_notif_kind_contribution' => "taux de cotisation",
    'rate_notif_subject' => "Modification de taux :verb",
    'rate_notif_verb_approved' => "approuvée",
    'rate_notif_verb_rejected' => "rejetée",
    'rate_notif_body_approved' => "Votre modification de taux légal (:label) a été approuvée et est active.",
    'rate_notif_body_rejected' => "Votre modification de taux légal (:label) a été rejetée : :reason",
    'rate_reject_reason_required' => "Un motif de rejet est obligatoire.",
];
