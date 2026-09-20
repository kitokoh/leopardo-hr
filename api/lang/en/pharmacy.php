<?php

declare(strict_types=1);

// BC-30 PHARMACY (PHARMA-001..007, #7798-#7804) — user-facing messages of the
// PharmaManager vertical (PA2-I18N-007).
return [
    'solution_inactive' => 'The PharmaManager solution is not active for this tenant.',
    'insufficient_stock' => 'Insufficient stock for product #:product: :requested requested, :available available (non-expired batches).',
    'invalid_transition' => 'Invalid transition: :from → :to.',
    'prescription_required' => 'Product ":product" requires a prescription: provide prescription_id.',
    'prescription_not_found' => 'Prescription not found.',
    'product_not_found' => 'Product not found.',
    'batch_not_found' => 'Batch not found.',
    'batch_not_found_return' => 'Batch not found for the reversal.',
    'order_line_not_found' => 'Purchase order line not found.',
    'quantity_received_positive' => 'The received quantity must be strictly positive.',
    'quantity_dispensed_positive' => 'The dispensed quantity must be strictly positive.',
    'adjustment_delta_nonzero' => 'The adjustment delta cannot be zero.',
    'adjustment_reason_required' => 'The adjustment reason is required.',
    'void_reason_required' => 'The void reason is required.',
    'invalid_adjustment_type' => 'Invalid adjustment type.',
    'invalid_dispense_type' => 'Invalid dispense type.',
    'empty_order' => 'A purchase order must contain at least one line.',
    'empty_receipt' => 'No line to receive.',
    'empty_sale' => 'A sale must contain at least one line.',
    'over_receipt' => 'Over-receipt refused on line #:line: :ordered ordered, :received already received, :proposed proposed.',
];
