<?php

declare(strict_types=1);

/**
 * Issue #1812 — Mapping pays → fêtes islamiques applicables.
 *
 * Les fêtes islamiques sont mobiles (calendrier hégirien, −10/11 jours par
 * an). Une même fête peut durer 1 ou 2 jours selon le pays (ex. Aïd el-Adha
 * fêté 2 jours au Cameroun, 1 jour en Côte d'Ivoire).
 *
 * Le mapping est la source de vérité pour `IslamicCalendarService` :
 * seules les entrées de la table `islamic_calendar` dont le holiday_key est
 * listé pour un pays sont injectées dans le calcul des jours ouvrés de ce
 * pays (via `PublicHolidayService::getHolidays()`).
 */

return [
    // Libellé par défaut (générique) de chaque fête.
    'labels' => [
        'eid_al_fitr' => 'Aïd el-Fitr',
        'eid_al_adha' => 'Aïd el-Adha / Tabaski',
        'mawlid' => 'Maouloud (naissance du Prophète)',
        'tahmarit' => 'Tamkharit (Achoura)',
        'muharram' => 'Nouvel an hégirien (Muharram)',
    ],

    // Fêtes applicables par pays (code ISO 3166-1 alpha-2).
    // durée = nombre de jours chômés dans le pays.
    'countries' => [
        'DZ' => [
            'eid_al_fitr' => ['duration' => 1, 'name' => 'Aïd el-Fitr'],
            'eid_al_adha' => ['duration' => 2, 'name' => 'Aïd el-Adha'],
            'mawlid' => ['duration' => 1, 'name' => 'Maouloud'],
            'muharram' => ['duration' => 1, 'name' => 'Nouvel an hégirien'],
        ],
        'MA' => [
            'eid_al_fitr' => ['duration' => 2, 'name' => 'Aïd el-Fitr'],
            'eid_al_adha' => ['duration' => 2, 'name' => 'Aïd el-Adha'],
            'mawlid' => ['duration' => 1, 'name' => 'Maouloud'],
            'muharram' => ['duration' => 1, 'name' => 'Nouvel an hégirien'],
        ],
        'TN' => [
            'eid_al_fitr' => ['duration' => 2, 'name' => 'Aïd el-Fitr'],
            'eid_al_adha' => ['duration' => 2, 'name' => 'Aïd el-Adha'],
        ],
        'CM' => [
            'eid_al_fitr' => ['duration' => 1, 'name' => 'Aïd el-Fitr'],
            'eid_al_adha' => ['duration' => 2, 'name' => 'Aïd el-Adha (Tabaski)'],
            'mawlid' => ['duration' => 1, 'name' => 'Maouloud'],
        ],
        'CI' => [
            'eid_al_fitr' => ['duration' => 1, 'name' => 'Aïd el-Fitr'],
            'eid_al_adha' => ['duration' => 1, 'name' => 'Aïd el-Adha (Tabaski)'],
            'mawlid' => ['duration' => 1, 'name' => 'Maouloud'],
        ],
        'SN' => [
            'eid_al_fitr' => ['duration' => 1, 'name' => 'Korité (Aïd el-Fitr)'],
            'eid_al_adha' => ['duration' => 1, 'name' => 'Tabaski (Aïd el-Adha)'],
            'mawlid' => ['duration' => 1, 'name' => 'Gamou (Maouloud)'],
            'tahmarit' => ['duration' => 1, 'name' => 'Tamkharit'],
        ],
    ],
];
