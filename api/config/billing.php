<?php

/*
|--------------------------------------------------------------------------
| Configuration Billing / Essai
|--------------------------------------------------------------------------
| Source unique de la durée d'essai (décision propriétaire D-E4-01,
| commit 594c68f2 — essai vitrine = 14 jours, PRs #2944/#3135).
| Toute surface (provisioning, réponse verify, seeders, copy vitrine)
| doit lire cette constante — jamais de littéral 14/30 éparpillé.
*/

return [
    // Durée de l'essai en jours (offre canonique).
    'trial_days' => (int) env('TRIAL_DAYS', 14),

    // #7764 — checkout sandbox (crédits IA) : opt-in EXPLICITE dev/staging,
    // même sémantique et même variable que le front (`SANDBOX_CHECKOUT`,
    // #2628) — jamais déduit d'une clé Stripe absente, sinon la production
    // simulerait silencieusement des paiements.
    'sandbox_checkout' => (bool) env('SANDBOX_CHECKOUT', false),
];
