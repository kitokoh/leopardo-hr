<?php

return [
    'order' => [
        'quantity_positive' => 'Quantité strictement positive requise.',
    ],
    'notifications' => [
        'order_ready_title' => "Commande prête",
        'order_ready_body' => "Commande %s prête à être servie (table %s).",
    ],
    'reservation' => [
        'deposit_exists' => "Un dépôt existe déjà pour cette réservation.",
        'deposit_on_terminated' => "Impossible d'enregistrer un dépôt sur une réservation terminée.",
    ],
    'loyalty' => [
        'opt_in_required' => "L'opt-in RGPD est requis pour activer la fidélité.",
    ],
    'commands' => [
        'outbox_description' => "Consomme les événements d'outbox RestaurantManager dus (idempotent, retry avec backoff, dead-letter).",
        'stock_alert_description' => "Publie les alertes de seuil de stock RestaurantManager (idempotent, une par jour).",
        'reservation_jobs_description' => "No-show + rappels de réservation RestaurantManager (idempotent).",
        'stock_alert_scan_result' => "%s (%s) : %d alerte(s) créée(s), %d doublon(s) ignoré(s).",
        'stock_alert_total' => "Total : %d alerte(s) créée(s), %d doublon(s).",
    ],
];
