<?php

declare(strict_types=1);

// BC-37 AGRICULTURE — Farm OS (#8549-#8570). Catalogue i18n du module :
// PA2-I18N-007 / issue #5432 — aucun libellé utilisateur en dur côté backend,
// tout message passe par __('farm.xxx').
return [
    // Liste de contrôle de l'étape 4 (provisionnement) — l'état `done` est
    // relu en base, seul le libellé est traduit ici.
    'checklist' => [
        'activity_declared' => 'Activité agricole déclarée',
        'tools_decided' => 'Outils activés',
        'solution_active' => 'Solution Farm OS active',
        'first_site' => 'Première exploitation / site créé',
        'first_parcel' => 'Première parcelle créée',
        'first_operation' => 'Première opération planifiée',
        'workers_ready' => 'Outil travailleurs activé',
    ],

    // T1.3 — notification d'affectation d'une intervention (BC-13 COMMS).
    'notifications' => [
        'operation_assigned_title' => 'Nouvelle intervention affectée',
        'operation_assigned_body' => 'L\'intervention « :title » vous a été affectée.',
    ],
];
