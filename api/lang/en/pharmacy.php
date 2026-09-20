<?php

return [
    'invalid_received_quantity' => 'The received quantity must be strictly positive.',
    'over_receipt' => 'Over-receipt rejected on line #:line: :ordered ordered, :received already received, :proposed proposed.',
    'adjustment_delta_zero' => 'The adjustment delta cannot be zero.',
    'invalid_dispensed_quantity' => 'The dispensed quantity must be strictly positive.',
    'invalid_movement_type' => 'Invalid dispensing type.',
    'insufficient_stock' => 'Insufficient stock for product #:product: :requested requested, :available available (non-expired batches).',
    'prescription_required' => 'Product ":product" requires a prescription: provide prescription_id.',
    'empty_receipt' => 'No line to receive.',
];
