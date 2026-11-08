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

    /*
    |--------------------------------------------------------------------------
    | Analyse antivirus des CV (#9217)
    |--------------------------------------------------------------------------
    |
    | Le CV déposé par un candidat est ensuite téléchargeable par un recruteur :
    | un document piégé est un vecteur d'attaque classique contre le poste de
    | travail de celui qui l'ouvre. La validation par type MIME et taille ne
    | regarde que l'enveloppe, jamais le contenu.
    |
    | `driver` :
    |   - `clamav` — analyse réelle via clamd (INSTREAM).
    |   - `none`   — aucun moteur. Le verdict est alors explicitement
    |                `unavailable`, JAMAIS `clean` : un CV non analysé ne doit
    |                pas être présenté comme vérifié.
    |
    | `policy` :
    |   - `reject`     (défaut) — un CV infecté est refusé (422) et supprimé.
    |   - `quarantine` — le CV infecté est conservé et marqué, pour analyse
    |                    manuelle par l'équipe sécurité.
    |
    | Un scanner INDISPONIBLE n'est jamais un motif de rejet : une panne
    | d'infrastructure ne doit pas empêcher un candidat de postuler. Le verdict
    | est persisté, donc auditable.
    */
    'antivirus' => [
        'driver' => env('RECRUITMENT_AV_DRIVER', 'none'),
        'policy' => env('RECRUITMENT_AV_POLICY', 'reject'),
        'host' => env('RECRUITMENT_AV_HOST', '127.0.0.1'),
        'port' => (int) env('RECRUITMENT_AV_PORT', 3310),
        'timeout' => (int) env('RECRUITMENT_AV_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Identifiants des agrégateurs d'offres (#9218)
    |--------------------------------------------------------------------------
    |
    | Repli utilisé quand rien n'est saisi dans l'écran super admin. La saisie
    | en base PRIME sur ces valeurs (cf. JobBoardCredentialResolver) : un
    | opérateur qui a explicitement configuré un connecteur ne doit pas voir ses
    | identifiants remplacés par un déploiement.
    |
    | Ces valeurs sont lues via `config()` et non `env()` : `env()` hors du
    | répertoire `config/` renvoie null dès que la configuration est mise en
    | cache (`php artisan config:cache`), ce qui désactiverait silencieusement
    | tous les connecteurs en production.
    */
    'job_boards' => [
        'adzuna' => [
            'app_id' => env('RECRUITMENT_JOBBOARD_ADZUNA_APP_ID'),
            'app_key' => env('RECRUITMENT_JOBBOARD_ADZUNA_APP_KEY'),
        ],
        'jooble' => [
            'api_key' => env('RECRUITMENT_JOBBOARD_JOOBLE_API_KEY'),
        ],
        'indeed' => [
            'publisher_id' => env('RECRUITMENT_JOBBOARD_INDEED_PUBLISHER_ID'),
            'api_key' => env('RECRUITMENT_JOBBOARD_INDEED_API_KEY'),
        ],
        'linkedin' => [
            'client_id' => env('RECRUITMENT_JOBBOARD_LINKEDIN_CLIENT_ID'),
            'client_secret' => env('RECRUITMENT_JOBBOARD_LINKEDIN_CLIENT_SECRET'),
            'access_token' => env('RECRUITMENT_JOBBOARD_LINKEDIN_ACCESS_TOKEN'),
        ],
        'glassdoor' => [
            'partner_id' => env('RECRUITMENT_JOBBOARD_GLASSDOOR_PARTNER_ID'),
            'partner_key' => env('RECRUITMENT_JOBBOARD_GLASSDOOR_PARTNER_KEY'),
        ],
    ],
];
