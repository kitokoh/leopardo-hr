<?php

declare(strict_types=1);

/**
 * Issue #9808 — révocation de jetons et bannissement de compte.
 *
 * Ce fichier ne porte QUE des réglages de la liste de révocation. Les durées
 * de vie des jetons restent dans `config/sanctum.php` (source unique,
 * `TokenExpiryService`) : les dupliquer ici créerait deux vérités.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Activation
    |--------------------------------------------------------------------------
    |
    | `false` désactive la vérification de la liste de révocation. Réservé aux
    | tests locaux : en production, une liste désactivée signifie qu'un jeton
    | révoqué continue de fonctionner, ce qui est exactement le défaut que
    | #9808 corrige. `TokenRevocationService` refuse donc de s'éteindre en
    | production (voir `AppServiceProvider`).
    |
    */
    'enabled' => (bool) env('AUTH_REVOCATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Store de cache
    |--------------------------------------------------------------------------
    |
    | `null` = store par défaut (`CACHE_STORE`, Redis en production). Un store
    | dédié est possible pour isoler la liste de révocation du cache applicatif
    | (une purge de cache métier ne doit pas ressusciter un jeton révoqué).
    |
    */
    'store' => env('AUTH_REVOCATION_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Préfixe des clés
    |--------------------------------------------------------------------------
    |
    | Les clés sont `{prefix}auth:revoked:token:{id}` et
    | `{prefix}auth:banned:{type}:{id}`. Le préfixe du store s'y ajoute encore ;
    | celui-ci sert à distinguer la liste de révocation dans un Redis partagé.
    |
    */
    'key_prefix' => env('AUTH_REVOCATION_KEY_PREFIX', 'leopardo:'),

];
