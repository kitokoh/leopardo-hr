<?php

declare(strict_types=1);

/**
 * MAT-010 (#5868) — Registre versionné des feature flags et kill switches (BC-01 PLATFORM).
 *
 * Source de vérité des flags connus : clé → portée (module/solution), défaut,
 * version d'introduction et possibilité de kill switch. Politique par défaut
 * FAIL-CLOSED : un flag inconnu est désactivé, un kill switch coupe un flag
 * pour TOUS les tenants (sans suppression de données — l'activation reste
 * stockée, seule la résolution est figée à false).
 *
 * Surcharge d'exploitation : chaque kill switch est overridable par env
 * `FEATURE_FLAG_KILL_<CLE_MAJUSCULE>=1|0` (ex. FEATURE_FLAG_KILL_LEO_AI=1).
 */
return [
    'version' => '1.0.0',

    // fail_closed : flag inconnu ou kill-switché ⇒ désactivé.
    'default_policy' => 'fail_closed',

    // Kill switches globaux (déployés par config/env). true = coupé partout.
    'kill_switches' => [
        // 'leo_ai' => true,
    ],

    'flags' => [
        'rh' => [
            'scope' => 'module',
            'default' => true,
            'since' => '4.0.0',
            'killable' => false,
            'description' => 'Module RH — socle de l\'application (non killable).',
        ],
        'finance' => [
            'scope' => 'module',
            'default' => false,
            'since' => '4.10.0',
            'killable' => true,
            'description' => 'Module Finance (paie, comptabilité, dépenses).',
        ],
        'cameras' => [
            'scope' => 'module',
            'default' => false,
            'since' => '4.16.0',
            'killable' => true,
            'description' => 'Caméras & vidéosurveillance (BC-19 DEVICE).',
        ],
        'muhasebe' => [
            'scope' => 'module',
            'default' => false,
            'since' => '4.16.0',
            'killable' => true,
            'description' => 'Module comptabilité Turquie (muhasebe).',
        ],
        'leo_ai' => [
            'scope' => 'module',
            'default' => false,
            'since' => '4.16.0',
            'killable' => true,
            'description' => 'Assistant IA Leopardo (BC-23 AI).',
        ],
        'fuel_station' => [
            'scope' => 'solution',
            'default' => false,
            'since' => '4.24.0',
            'killable' => true,
            'description' => 'Solution FuelStation — pilote terrain (BC-15 FUEL).',
        ],
    ],
];
