<?php

return [
    // Document types (issue #5224)
    'document_type_invoice' => 'Invoice',
    'document_type_proforma' => 'Proforma',
    'document_type_quote' => 'Quote',
    'document_type_credit_note' => 'Credit note',
    'document_type_delivery_note' => 'Delivery note',
    'document_type_receipt' => 'Receipt',

    // Statuses
    'status_draft' => 'Draft',
    'status_sent' => 'Sent',
    'status_partially_paid' => 'Partially paid',
    'status_paid' => 'Paid',
    'status_cancelled' => 'Cancelled',
    'status_overdue' => 'Overdue',

    // Header / parties
    'number' => 'No.',
    'issue_date' => 'Issue date',
    'due_date' => 'Due date',
    'delivery_date' => 'Delivery date',
    'from' => 'From',
    'to' => 'Bill to',
    'nif' => 'Tax ID',

    // Lines
    'description' => 'Description',
    'quantity' => 'Qty',
    'unit_price' => 'Unit price',
    'discount' => 'Discount',
    'amount' => 'Amount',

    // Totals
    'subtotal_ht' => 'Subtotal',
    'tax' => 'Tax',
    'total_ttc' => 'Total',
    'paid' => 'Paid',
    'remaining' => 'Balance due',
    'page' => 'Page',
    'page_of' => 'of',

    'no_lines' => 'No lines',

    // Footer
    'legal_mentions' => 'Legal mentions',

    // API business errors (issue #5227)
    'error_invalid_document_type' => 'Invalid document type.',
    'error_document_requires_line' => 'A document must have at least one line.',
    'error_only_draft_can_be_sent' => 'Only a draft can be sent.',
    'error_send_without_lines' => 'Cannot send a document without lines.',
    'error_contact_required_for_invoice' => 'A customer contact is required to send an invoice or a credit note.',
    'error_payment_on_closed_document' => 'A paid or cancelled document cannot receive payments.',
    'error_payment_amount_positive' => 'The payment amount must be strictly positive.',
    'error_payment_exceeds_total_ttc' => 'The total of payments exceeds the document total including tax.',
    'error_paid_document_cannot_cancel' => 'A paid document cannot be cancelled.',
    'error_credit_note_requires_invoice' => 'A credit note must be linked to an invoice.',
    'error_credit_note_source_not_sent' => 'A cancelled or draft invoice cannot generate a credit note.',
    'error_source_invoice_already_paid' => 'The source invoice is already fully paid: no credit note is possible.',
    'error_credit_note_exceeds_remaining' => 'The credit note amount exceeds the remaining balance of the source invoice.',
    'error_company_context_required' => 'Company context is required.',

    'error_vat_period_invalid' => 'Invalid VAT declaration period (expected format: YYYY-MM).',
    'error_unknown_series' => 'Unknown series: ":key" is not a document type (:allowed).',

    // Validation (issue #5227)
    'validation_amount_required' => 'The amount is required.',
    'validation_amount_positive' => 'The amount must be strictly positive.',
    'validation_payment_method_invalid' => 'Invalid payment method (cash, bank_transfer, check, card, other).',
];
