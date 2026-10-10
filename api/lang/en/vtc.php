<?php

declare(strict_types=1);

// BC-34 VTC (VTC-02..06, #8358-#8362) — VTC/taxi vertical messages
// (PA2-I18N-007: no hardcoded strings, everything goes through this catalog).
return [
    'ride_creation_failed' => 'Unable to create the VTC ride after :attempts attempts.',
    'availability_locked' => 'Availability cannot be changed from status :status (complete the ride or contact the operator).',
    'purge_positions_description' => 'GDPR purge of VTC driver positions beyond retention (idempotent, audited).',
    'purge_positions_done_log' => 'vtc:purge-positions — GDPR purge of driver positions executed.',
    'purge_positions_done_cli' => 'VTC purge complete: :deleted position(s) deleted (retention :days d, cutoff :cutoff).',
    'driver_delete_has_rides' => 'Driver with rides: deletion not allowed (prefer suspension — history is preserved).',
    'driver_user_not_found' => 'Employee account not found in this tenant.',
    'driver_vehicle_not_found' => 'Vehicle not found in this tenant.',
    'fare_profile_delete_in_use' => 'Fare profile referenced by rides: deletion not allowed (rides keep their historical quote).',
    'vehicle_delete_assigned' => 'Vehicle still assigned to a driver: remove the assignment before deletion.',
    'vehicle_plate_taken' => 'Plate :plate is already registered for this tenant.',
];
