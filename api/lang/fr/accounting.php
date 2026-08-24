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

    // Pied de page
    'legal_mentions' => 'Mentions légales',

    // Erreurs métier API (issue #5227)
    'error_invalid_document_type' => 'Type de document invalide.',
    'error_document_requires_line' => 'Un document doit avoir au moins une ligne.',
    'error_only_draft_can_be_sent' => 'Seul un brouillon peut être envoyé.',
    'error_send_without_lines' => 'Impossible d\'envoyer un document sans ligne.',
    'error_contact_required_for_invoice' => 'Un contact client est requis pour envoyer une facture ou un avoir.',
    'error_payment_on_closed_document' => 'Un document payé ou annulé ne peut pas recevoir de paiement.',
    'error_payment_amount_positive' => 'Le montant du paiement doit être strictement positif.',
    'error_payment_exceeds_total_ttc' => 'Le cumul des paiements dépasse le total TTC du document.',
    'error_paid_document_cannot_cancel' => 'Un document payé ne peut pas être annulé.',
    'error_credit_note_requires_invoice' => 'Un avoir doit être lié à une facture.',
    'error_credit_note_source_not_sent' => 'Une facture annulée ou brouillon ne peut pas générer d\'avoir.',
    'error_source_invoice_already_paid' => 'La facture source est déjà entièrement payée : aucun avoir possible.',
    'error_credit_note_exceeds_remaining' => 'Le montant de l\'avoir dépasse le reste à payer de la facture source.',
    'error_company_context_required' => 'Contexte entreprise requis.',
    'error_vat_period_invalid' => 'Période de déclaration TVA invalide (format attendu : YYYY-MM).',
    'error_unknown_series' => 'Série inconnue : « :key » n\'est pas un type de document (:allowed).',

    // Validation (issue #5227)
    'validation_amount_required' => 'Le montant est requis.',
    'validation_amount_positive' => 'Le montant doit être strictement positif.',
    'validation_payment_method_invalid' => 'Méthode de paiement invalide (cash, bank_transfer, check, card, other).',
];
