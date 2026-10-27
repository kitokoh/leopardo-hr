<?php

declare(strict_types=1);

// BC-32 HOSPITALITY (HOSP-001..008, EPIC #7951) — messages utilisateur de la
// verticale HospitalityManager (PA2-I18N-007 : jamais de français en dur dans
// les services/exceptions/commandes, tout passe par ce catalogue).
return [
    'solution_inactive' => 'La solution HospitalityManager n\'est pas active pour ce tenant.',
    'room_type_unavailable' => 'Ce type de chambre n\'est pas disponible pour cet établissement.',
    'no_availability' => 'Aucune disponibilité pour ce type de chambre sur cet intervalle.',
    'invalid_transition' => 'Transition de réservation invalide (:from → :target).',
    'checkout_before_checkin' => 'La date de départ doit être postérieure à la date d\'arrivée.',
    'unit_room_type_mismatch' => 'L\'unité affectée doit appartenir au même type de chambre que la réservation.',
    'invalid_lease_transition' => 'Transition de bail invalide (:from → :target).',
    'lease_terminal_immutable' => 'Un bail terminé ne peut plus être modifié : ses conditions sont figées par les échéances déjà émises.',
    'rent_amount_must_be_positive' => 'Le montant encaissé doit être strictement positif.',
    'rent_already_settled' => 'Cette échéance est déjà soldée : aucun encaissement supplémentaire n\'est possible.',
    'rent_amount_exceeds_outstanding' => 'Le montant encaissé (:amount) dépasse le reste dû (:outstanding).',
    'console' => [
        'expire_pending_description' => 'Expire les réservations hospitality pending dépassées : annulation + libération inventaire (HOSP-004/#7946).',
        'expire_pending_no_tenant' => 'Aucun tenant actif — rien à expirer.',
        'expire_pending_tenant_summary' => 'Tenant :company : :count réservation(s) hospitality expirée(s).',
        'expire_pending_total' => 'Total : :count réservation(s) hospitality expirée(s).',
    ],
];
