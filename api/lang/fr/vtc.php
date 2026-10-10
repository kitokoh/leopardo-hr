<?php

declare(strict_types=1);

// BC-34 VTC (VTC-02..06, #8358-#8362) — messages de la verticale VTC/taxi
// (PA2-I18N-007 : jamais de français en dur, tout passe par ce catalogue).
return [
    'ride_creation_failed' => 'Création de course VTC impossible après :attempts tentatives.',
    'availability_locked' => 'Disponibilité non modifiable depuis le statut :status (clôturer la course ou contacter l\'exploitant).',
    'purge_positions_description' => 'Purge RGPD des positions chauffeurs VTC au-delà de la rétention (idempotente, auditée).',
    'purge_positions_done_log' => 'vtc:purge-positions — purge RGPD des positions chauffeurs exécutée.',
    'purge_positions_done_cli' => 'Purge VTC terminée : :deleted position(s) supprimée(s) (rétention :days j, cutoff :cutoff).',
    'driver_delete_has_rides' => 'Chauffeur ayant des courses : suppression impossible (préférer la suspension — l\'historique est conservé).',
    'driver_user_not_found' => 'Compte employé introuvable dans ce tenant.',
    'driver_vehicle_not_found' => 'Véhicule introuvable dans ce tenant.',
    'fare_profile_delete_in_use' => 'Grille tarifaire référencée par des courses : suppression impossible (les courses conservent leur devis historisé).',
    'vehicle_delete_assigned' => 'Véhicule encore affecté à un chauffeur : retirer l\'affectation avant suppression.',
    'vehicle_plate_taken' => 'La plaque :plate est déjà enregistrée pour ce tenant.',
];
