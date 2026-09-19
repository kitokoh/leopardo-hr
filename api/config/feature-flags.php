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
        // #7432 — Formation (outil HORIZONTAL, BC-04 HR) : le flag tenant est
        // piloté par l'admin plateforme (`PATCH /platform/companies/{id}/features`)
        // et par la dotation du tenant. Il est déclaré ici pour être exposé par
        // `FeatureFlag::for()` (donc par `/auth/me`) — sans quoi la clé
        // `training` restait invisible du client, même activée.
        'training' => [
            'scope' => 'module',
            'default' => false,
            'since' => '4.25.0',
            'killable' => true,
            'description' => 'Module Formation — outil horizontal (BC-04 HR) : catalogue, sessions, inscriptions.',
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
        'healthmanager' => [
            'scope' => 'solution',
            'default' => false,
            'since' => '4.34.0',
            'killable' => true,
            'description' => 'Solution HealthManager (hôpitaux et cliniques privées : patients, rendez-vous, hospitalisations, facturation des soins).',
        ],
        'travelagency' => [
            'scope' => 'solution',
            'default' => false,
            'since' => '4.25.0',
            'killable' => true,
            'description' => 'Solution Agence de voyage (ventes, réservations, check-in).',
        ],
        // BC-27 SHOWCASE — module HORIZONTAL « Site vitrine » (site public de
        // l'entreprise créé en 1 clic par le responsable du tenant). Le module
        // serveur existait (`app/Modules/Showcase`, routes `/api/v1/showcase/*`,
        // gate `module.showcase`) et le drapeau tenant était bien lu par
        // `Company::hasFeature('company_showcase')` — mais il était ABSENT de ce
        // registre et de `Company::KNOWN_MODULES` : `FeatureFlag::for()`
        // l'ignorait donc et /auth/me ne remontait jamais la clé (même classe de
        // défaut que #7235 pour accounting/crm/travel). Déclaré ici, le module
        // devient activable par l'admin plateforme et visible côté client.
        'company_showcase' => [
            'scope' => 'module',
            'default' => false,
            'since' => '4.32.0',
            'killable' => true,
            'description' => 'Site vitrine public de l\'entreprise (création 1-clic, sections, thème, publication).',
        ],
        // BC-17 RETAIL (#7672) — module vendeur générique : gestion des
        // produits & catégories (fondations backend). Défaut OFF (fail-closed),
        // activation par tenant via l'admin plateforme.
        'retail' => [
            'scope' => 'solution',
            'default' => false,
            'since' => '4.33.0',
            'killable' => true,
            'description' => 'Module Retail — vendeur générique (BC-17) : produits, catégories, publication.',
        ],
        // BC-29 COMMUNICATION (R0, #7685) — module transversal « Communication »
        // (boîte mail connectée Gmail + IA, spec
        // docs/specifications/MODULE_COMMUNICATION_EMAIL_IA.md). Déclaré ici ET
        // dans `Company::KNOWN_MODULES` (leçon company_showcase ci-dessus :
        // sans l'entrée de ce registre, `FeatureFlag::for()` ignore la clé et
        // /auth/me ne la remonte jamais) pour que l'admin plateforme puisse
        // l'activer (PATCH /platform/companies/{company}/features).
        // Fail-closed : désactivé par défaut, gate serveur `module.communication`
        // sur les routes /api/v1/communication/*.
        'communication' => [
            'scope' => 'module',
            'default' => false,
            'since' => '4.33.0',
            'killable' => true,
            'description' => 'Communication (boîte mail connectée + IA) : intégrations Gmail, classification, relances et réponses assistées.',
        ],
    ],
];
