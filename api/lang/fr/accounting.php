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
];
