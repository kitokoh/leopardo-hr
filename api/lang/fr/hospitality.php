<?php

declare(strict_types=1);

// BC-32 HOSPITALITY (HOSP-001..008, EPIC #7951) — messages utilisateur de la
// verticale HospitalityManager (PA2-I18N-007 : jamais de français en dur dans
// les services/exceptions/commandes, tout passe par ce catalogue).
return [
    'solution_inactive' => 'La solution HospitalityManager n\'est pas active pour ce tenant.',
    'no_availability' => 'Aucune disponibilité pour ce type de chambre sur cet intervalle.',
    'invalid_transition' => 'Transition de réservation invalide (:from → :target).',
    'console' => [
        'expire_pending_description' => 'Expire les réservations hospitality pending dépassées : annulation + libération inventaire (HOSP-004/#7946).',
        'expire_pending_no_tenant' => 'Aucun tenant actif — rien à expirer.',
        'expire_pending_tenant_summary' => 'Tenant :company : :count réservation(s) hospitality expirée(s).',
        'expire_pending_total' => 'Total : :count réservation(s) hospitality expirée(s).',
    ],
];
