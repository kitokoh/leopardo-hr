<?php

declare(strict_types=1);

/**
 * SUPERAPP-P1 (#8760) — Réglages de la passerelle publique « Leopardo Public ».
 *
 * Un seul réglage pour l'instant : la durée de conservation des demandes de
 * contact (`public_contact_requests`), appliquée à la création
 * (`retention_until`) et exploitable par la purge RGPD. Valeur par défaut
 * volontairement courte : une demande de contact non traitée n'a pas vocation
 * à être conservée indéfiniment.
 */
return [
    'public_contact_retention_days' => (int) env('PUBLIC_CONTACT_RETENTION_DAYS', 120),
];
