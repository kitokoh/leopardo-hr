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

    // Email (issue #5225)
    'email_subject' => 'Document :number',
    'email_heading' => 'Your document',
    'email_body' => 'Hello, your document :number is available.',
    'email_button' => 'View my document',
    'email_expires' => 'This link expires on :date.',
    'email_footer' => 'This is an automated email — please do not reply.',

    // Footer
    'legal_mentions' => 'Legal mentions',

    // Validation (issue #5227)
    'validation' => [
        'amount_required' => 'The amount is required.',
        'amount_min' => 'The amount must be strictly positive.',
        'method_invalid' => 'Invalid payment method (cash, bank_transfer, check, card, other).',
        'series_unknown' => 'Unknown series: « :key » is not a document type (:allowed).',
        // Rapprochement / profondeur (issue #5422)
        'year_required' => 'The year is required.',
        'year_integer' => 'The year must be an integer.',
        'year_range' => 'The year must be between 2000 and 2100.',
        'period_required' => 'The period is required (YYYY-MM).',
        'period_invalid' => 'Invalid period. Use the YYYY-MM format.',
        'letter_required' => 'The lettering code is required.',
        'letter_max' => 'The lettering code cannot exceed 32 characters.',
        'entry_ids_required' => 'Select at least two entries to letter.',
        'entry_ids_integer' => 'Invalid entry identifiers.',
        'entry_ids_min' => 'Lettering requires at least two entries.',
        'year_between' => 'The year must be between 2000 and 2100.',
    ],

    'bank_file_required' => 'The CSV file is required.',
    'bank_file_mimes' => 'The file must be CSV.',
    'bank_period_required' => 'The statement period is required (YYYY-MM).',
    'bank_period_format' => 'The period must use the YYYY-MM format.',
    'bank_reference_required' => 'The import reference is required.',
    'bank_payment_required' => 'The payment to reconcile is required.',
    'bank_payment_exists' => 'The selected payment does not exist.',
    'errors' => [
        'gateway_checkout_failed' => 'The payment gateway is temporarily unavailable. Please try again later.',
        'payment_amount_positive' => 'The payment amount must be strictly positive.',
        'wf_invalid_type' => 'Invalid document type.',
        'wf_requires_lines' => 'A document must have at least one line.',
        'wf_send_draft_only' => 'Only a draft can be sent.',
        'wf_send_no_lines' => 'Cannot send a document without lines.',
        'wf_send_requires_contact' => 'A client contact is required to send an invoice or a credit note.',
        'wf_payment_receive_status' => 'A paid or cancelled document cannot receive a payment.',
        'wf_payment_over_total' => 'The total payments exceed the total amount of the document.',
        'wf_cancel_status' => 'A paid document cannot be cancelled.',
        'wf_credit_note_requires_invoice' => 'A credit note must be linked to an invoice.',
        'wf_source_invoice_not_issuable' => 'A cancelled or draft invoice cannot generate a credit note.',
        'wf_source_invoice_paid' => 'The source invoice is already fully paid: no credit note is possible.',
        'wf_credit_exceeds_remaining' => 'The credit note amount exceeds the remaining balance of the source invoice.',
        'bank_line_empty' => 'Empty line skipped.',
        'bank_line_invalid_date' => 'Invalid date: ":value".',
        'bank_line_missing_label' => 'Missing label.',
        'bank_line_invalid_amount' => 'Invalid amount: ":value".',
        'bank_empty_file' => 'The CSV file is empty.',
        'bank_missing_columns' => 'Invalid header: required columns missing (:columns).',
        'wf_company_context' => 'Company context required.',
        'statement_year_invalid' => 'Invalid fiscal year.',
        'statement_period_invalid' => 'Invalid accounting period (YYYY-MM).',
        'vat_period_invalid' => 'Invalid period. Use the YYYY-MM format.',
    ],

    // Default VAT labels (issue #5227)
    'tva_label_standard' => 'Standard VAT',
    'tva_label_sales_tax' => 'Sales tax',
    'tva_label_gst' => 'GST',
    'tva_label_reduced' => 'Reduced VAT',

    // Profondeur comptable (issue #5422)
    'chart_system_account_not_deletable' => 'System accounts (provisioned) cannot be deleted — deactivate them if needed.',
    'chart_account_has_entries' => 'This account carries journal entries and cannot be deleted.',
    'fec_no_entries' => 'No entries for this period — FEC export impossible.',
    'fiscal_year_already_closed' => 'This fiscal year is already closed or does not exist.',
    'lettering_unbalanced' => 'Lettering must be balanced: total debits must equal total credits.',
    'lettering_invalid' => 'Invalid lettering: entries must target the same account.',
    'lettering_already_used' => 'One or more entries are already lettered with another code.',
];
