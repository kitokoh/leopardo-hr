<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Recrutement — portail public d'offres
    |--------------------------------------------------------------------------
    |
    | BC-10 (#9100). Ces réglages pilotent la surface publique du portail
    | carrières et la synchronisation des agrégateurs d'offres.
    |
    */

    /*
    | Durée de conservation des candidatures, en jours, à compter du
    | consentement. Sert à renseigner `applicants.retention_until`, qui rend la
    | purge RGPD calculable sans interprétation. Configurable parce qu'une durée
    | codée en dur serait impossible à ajuster par pays.
    */
    'applicant_retention_days' => (int) env('RECRUITMENT_APPLICANT_RETENTION_DAYS', 730),

    /*
    | Nombre maximal d'offres importées par synchronisation d'un agrégateur.
    | Borne la mémoire et la durée du job : un flux de 50 000 offres ne doit pas
    | faire tomber le worker.
    */
    'sync_max_offers' => (int) env('RECRUITMENT_SYNC_MAX_OFFERS', 500),

    /*
    | Durée de mise en cache du flux XML (minutes). Le flux est lu par les
    | robots de Google Jobs / Indeed : il doit rester frais sans être recalculé
    | à chaque requête.
    */
    'feed_cache_minutes' => (int) env('RECRUITMENT_FEED_CACHE_MINUTES', 15),
];
