<?php

return [
    'order' => [
        'quantity_positive' => 'Quantity must be strictly positive.',
    ],
];
    'notifications' => [
        'order_ready_title' => "Order ready",
        'order_ready_body' => "Order %s is ready to be served (table %s).",
    'reservation' => [
        'deposit_exists' => "A deposit already exists for this reservation.",
        'deposit_on_terminated' => "Cannot record a deposit on a terminated reservation.",
    'loyalty' => [
        'opt_in_required' => "RGPD opt-in is required to activate loyalty.",
    'commands' => [
        'outbox_description' => "Consumes due RestaurantManager outbox events (idempotent, retry with backoff, dead-letter).",
        'stock_alert_description' => "Publishes RestaurantManager stock threshold alerts (idempotent, once a day).",
        'reservation_jobs_description' => "No-show + reservation reminders RestaurantManager (idempotent).",
        'stock_alert_scan_result' => "%s (%s): %d alert(s) created, %d duplicate(s) ignored.",
        'stock_alert_total' => "Total: %d alert(s) created, %d duplicate(s).",
