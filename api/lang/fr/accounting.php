<?php

return [
    // Types de document (issue #5224)
    'document_type_invoice' => 'Facture',
    'document_type_proforma' => 'Proforma',
    'document_type_quote' => 'Devis',
    'document_type_credit_note' => 'Avoir',
    'document_type_delivery_note' => 'Bordereau de livraison',
    'document_type_receipt' => 'Reçu',

    // Statuts
    'status_draft' => 'Brouillon',
    'status_sent' => 'Envoyé',
    'status_partially_paid' => 'Partiellement payé',
    'status_paid' => 'Payé',
    'status_cancelled' => 'Annulé',
    'status_overdue' => 'En retard',

    // En-tête / parties
    'number' => 'N°',
    'issue_date' => "Date d'émission",
    'due_date' => 'Échéance',
    'delivery_date' => 'Date de livraison',
    'from' => 'Émetteur',
    'to' => 'Client',
    'nif' => 'NIF',

    // Lignes
    'description' => 'Désignation',
    'quantity' => 'Qté',
    'unit_price' => 'PU HT',
    'discount' => 'Remise',
    'amount' => 'Montant HT',

    // Totaux
    'subtotal_ht' => 'Total HT',
    'tax' => 'TVA',
    'total_ttc' => 'Total TTC',
    'paid' => 'Payé',
    'remaining' => 'Reste à payer',
    'page' => 'Page',
    'page_of' => 'sur',

    'no_lines' => 'Aucune ligne',

    // Email (issue #5225)
    'email_subject' => 'Document :number',
    'email_heading' => 'Votre document',
    'email_body' => 'Bonjour, votre document :number est disponible.',
    'email_button' => 'Consulter mon document',
    'email_expires' => 'Ce lien expire le :date.',
    'email_footer' => 'Ceci est un envoi automatique — merci de ne pas répondre.',

    // Pied de page
    'legal_mentions' => 'Mentions légales',

    // Validation (issue #5227)
    'validation' => [
        'amount_required' => 'Le montant est requis.',
        'amount_min' => 'Le montant doit être strictement positif.',
        'method_invalid' => 'Méthode de paiement invalide (cash, bank_transfer, check, card, other).',
        'series_unknown' => 'Série inconnue : « :key » n\'est pas un type de document (:allowed).',
        'bank_file_required' => 'Le fichier CSV est requis.',
        'bank_file_mimes' => 'Le fichier doit être au format CSV.',
        'bank_period_required' => 'La période du relevé est requise (format AAAA-MM).',
        'bank_period_format' => 'La période doit être au format AAAA-MM.',
        'bank_reference_required' => 'La référence d\'import est requise.',
        'bank_payment_required' => 'Le paiement à rapprocher est requis.',
        'bank_payment_exists' => 'Le paiement sélectionné n\'existe pas.',
        // Rapprochement / profondeur (issue #5422)
        'year_required' => 'L\'année est obligatoire.',
        'year_integer' => 'L\'année doit être un nombre entier.',
        'year_range' => 'L\'année doit être comprise entre 2000 et 2100.',
        'period_required' => 'La période est obligatoire (format AAAA-MM).',
        'period_invalid' => 'Période invalide. Utilisez le format AAAA-MM.',
        'letter_required' => 'La lettre de lettrage est obligatoire.',
        'letter_max' => 'La lettre de lettrage ne peut pas dépasser 32 caractères.',
        'entry_ids_required' => 'Sélectionnez au moins deux écritures à lettrer.',
        'entry_ids_integer' => 'Identifiants d\'écritures invalides.',
        'entry_ids_min' => 'Le lettrage nécessite au moins deux écritures.',
        'year_between' => 'L\'année doit être comprise entre 2000 et 2100.',
    ],

    // Erreurs métier (issue #5227)
    'errors' => [
        'gateway_checkout_failed' => 'La passerelle de paiement est temporairement indisponible. Réessayez plus tard.',
        'payment_amount_positive' => 'Le montant du paiement doit être strictement positif.',
        'wf_invalid_type' => 'Type de document invalide.',
        'wf_requires_lines' => 'Un document doit avoir au moins une ligne.',
        'wf_send_draft_only' => 'Seul un brouillon peut être envoyé.',
        'wf_send_no_lines' => 'Impossible d\'envoyer un document sans ligne.',
        'wf_send_requires_contact' => 'Un contact client est requis pour envoyer une facture ou un avoir.',
        'wf_payment_receive_status' => 'Un document payé ou annulé ne peut pas recevoir de paiement.',
        'wf_payment_over_total' => 'Le cumul des paiements dépasse le total TTC du document.',
        'wf_cancel_status' => 'Un document payé ne peut pas être annulé.',
        'wf_credit_note_requires_invoice' => 'Un avoir doit être lié à une facture.',
        'wf_source_invoice_not_issuable' => 'Une facture annulée ou brouillon ne peut pas générer d\'avoir.',
        'wf_source_invoice_paid' => 'La facture source est déjà entièrement payée : aucun avoir possible.',
        'wf_credit_exceeds_remaining' => 'Le montant de l\'avoir dépasse le reste à payer de la facture source.',
        'bank_line_empty' => 'Ligne vide ignorée.',
        'bank_line_invalid_date' => 'Date invalide : « :value ».',
        'bank_line_missing_label' => 'Libellé manquant.',
        'bank_line_invalid_amount' => 'Montant invalide : « :value ».',
        'bank_empty_file' => 'Le fichier CSV est vide.',
        'bank_missing_columns' => 'En-tête invalide : colonnes requises absentes (:columns).',
        'wf_company_context' => 'Contexte entreprise requis.',
        'statement_year_invalid' => 'Année d\'exercice invalide.',
        'statement_period_invalid' => 'Période comptable invalide (format AAAA-MM).',
        'vat_period_invalid' => 'Période invalide. Utilisez le format AAAA-MM.',
    ],

    // Labels TVA par défaut (issue #5227)
    'tva_label_standard' => 'TVA standard',
    'tva_label_sales_tax' => 'Taxe de vente',
    'tva_label_gst' => 'TPS',
    'tva_label_reduced' => 'TVA réduite',


    // Profondeur comptable (issue #5422)
    'chart_system_account_not_deletable' => 'Les comptes système (provisionnés) ne peuvent pas être supprimés — désactivez-les si nécessaire.',
    'chart_account_has_entries' => 'Ce compte porte des écritures au journal et ne peut pas être supprimé.',
    'fec_no_entries' => 'Aucune écriture sur cette période — export FEC impossible.',
    'fiscal_year_already_closed' => 'Cet exercice est déjà clôturé ou n\'existe pas.',
    'lettering_unbalanced' => 'Le lettrage doit être équilibré : la somme des débits doit égaler la somme des crédits.',
    'lettering_invalid' => 'Lettrage invalide : les écritures doivent porter sur le même compte.',
    'lettering_already_used' => 'Une ou plusieurs écritures sont déjà lettrées avec une autre lettre.',
    // Rapprochement bancaire (issue #5435) — parité avec en/tr/ar (#5627 audit).
    'bank_file_mimes' => 'Le fichier doit être au format CSV.',
    'bank_file_required' => 'Le fichier CSV est requis.',
    'bank_payment_exists' => 'Le paiement sélectionné n\'existe pas.',
    'bank_payment_required' => 'Le paiement à rapprocher est requis.',
    'bank_period_format' => 'La période doit utiliser le format AAAA-MM.',
    'bank_period_required' => 'La période du relevé est requise (AAAA-MM).',
    'bank_reference_required' => 'La référence d\'import est requise.',
];



