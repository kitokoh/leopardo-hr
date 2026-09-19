<?php

return [
    'order' => [
        'quantity_positive' => 'Quantity must be strictly positive.',
    ],
    'notifications' => [
        'order_ready_title' => 'Order ready',
        'order_ready_body' => 'Order %s is ready to be served (table %s).',
    ],
    'reservation' => [
        'deposit_exists' => 'A deposit already exists for this reservation.',
        'deposit_on_terminated' => 'Cannot record a deposit on a terminated reservation.',
    ],
    'loyalty' => [
        'opt_in_required' => 'RGPD opt-in is required to activate loyalty.',
    ],
    'commands' => [
        'outbox_description' => 'Consumes due RestaurantManager outbox events (idempotent, retry with backoff, dead-letter).',
        'stock_alert_description' => 'Publishes RestaurantManager stock threshold alerts (idempotent, once a day).',
        'reservation_jobs_description' => 'No-show + reservation reminders RestaurantManager (idempotent).',
        'stock_alert_scan_result' => '%s (%s): %d alert(s) created, %d duplicate(s) ignored.',
        'stock_alert_total' => 'Total: %d alert(s) created, %d duplicate(s).',
    ],
    // RESTO-805 (#6226) / RESTO-902 (#7747) — public online ordering.
    'public_shop' => [
        'product_unavailable' => 'This product is not available for online ordering.',
        'product_not_served' => 'This product is not served by this establishment.',
        'currency_mismatch' => 'The product currency does not match the order currency.',
        'quantity_invalid' => 'Quantity must be strictly positive.',
        'empty_order' => 'The cart is empty.',
    ],
    // RESTO-902 (#7747) — public customer reviews.
    'public_reviews' => [
        'order_not_eligible' => 'A review is only possible for a served or delivered order.',
        'already_reviewed' => 'A review has already been submitted for this order.',
    ],
];
