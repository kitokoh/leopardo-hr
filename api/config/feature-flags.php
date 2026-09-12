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
        // A6 (#6853) — envoi des prompts vers un driver LLM cloud (groq/openai/
        // claude) : activé par tenant uniquement. Défaut OFF (fail-closed).
        'ai_cloud_allowed' => [
            'scope' => 'solution',
            'default' => false,
            'since' => '4.24.0',
            'killable' => true,
            'description' => 'Assistant IA (BC-23) : autorise l\'envoi vers les fournisseurs LLM cloud (RGPD — minimisation + audit requis).',
        ],
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
        // #7235 — Comptabilité : module HORIZONTAL de premier ordre (toute
        // entreprise, y compris un indépendant, peut en avoir besoin). Le
        // module existait (app/Modules/Accounting) mais était absent du
        // registre ET de `Company::KNOWN_MODULES` : il n'était donc jamais
        // exposé par /auth/me et le front l'affichait verrouillé alors que
        // l'API répondait (audit #7235).
        'accounting' => [
            'scope' => 'module',
            'default' => false,
            'since' => '4.25.0',
            'killable' => true,
            'description' => 'Comptabilité (journaux, grand livre, balance, FEC, lettrage).',
        ],
        // #7235 — Ces trois solutions étaient activables (elles sont dans
        // `Company::KNOWN_MODULES` et dans le catalogue de solutions) mais
        // absentes du registre : `FeatureFlag::for()` les ignorait donc, et
        // /auth/me ne les remontait jamais → la verticale choisie par le
        // client restait invisible côté web. Même correctif de cohérence pour
        // `crm` (module opt-in #5742), également absent du registre.
        'crm' => [
            'scope' => 'module',
            'default' => false,
            'since' => '4.25.0',
            'killable' => true,
            'description' => 'CRM client (comptes, contacts, opportunités, pipeline) — espace tenant.',
        ],
        'restaurant' => [
            'scope' => 'solution',
            'default' => false,
            'since' => '4.25.0',
            'killable' => true,
            'description' => 'Solution Restaurant (POS, cuisine, réservations, stock).',
        ],
        'edumanager' => [
            'scope' => 'solution',
            'default' => false,
            'since' => '4.25.0',
            'killable' => true,
            'description' => 'Solution EduManager (établissements scolaires, classes, notes).',
        ],
        'travelagency' => [
            'scope' => 'solution',
            'default' => false,
            'since' => '4.25.0',
            'killable' => true,
            'description' => 'Solution Agence de voyage (ventes, réservations, check-in).',
        ],
    ],
];
