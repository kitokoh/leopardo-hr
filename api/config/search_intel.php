<?php

declare(strict_types=1);

/**
 * GSI — configuration du module Growth & Search Intelligence.
 *
 * Aucune valeur n'est « magique » : ce que le module a besoin de savoir vit
 * ici, et rien n'est activé par défaut.
 *
 * `scan_base_url` est VIDE par défaut, volontairement : le scanner refuse de
 * tourner tant qu'aucune cible n'a été explicitement déclarée. Un scanner qui
 * se rabattrait sur une URL devinée balaierait un domaine qu'on ne lui a pas
 * demandé de surveiller.
 */
return [

    'scan' => [
        // Racine du parc à scanner (ex. https://leopardo-rh.com).
        'base_url' => env('SEARCH_INTEL_SCAN_BASE_URL'),

        // Chemins scannés par défaut, relatifs à `base_url`.
        'paths' => [
            '/',
        ],

        // Timeout HTTP par page, en secondes.
        'timeout' => (int) env('SEARCH_INTEL_SCAN_TIMEOUT', 10),
    ],

    'integrations' => [
        // Une synchronisation n'est autorisée que si le connecteur est
        // réellement configuré. Fail-closed : sans credentials, l'état reste
        // `not_connected` et la vue d'ensemble l'affiche tel quel.
        'enabled' => filter_var(env('SEARCH_INTEL_INTEGRATIONS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    ],

];
