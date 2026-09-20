<?php

declare(strict_types=1);

// #7739 — marqueur d'analyse statique (jamais défini au runtime applicatif).
//
// DOIT être chargé AVANT vendor/larastan/larastan/bootstrap.php (qui boote le
// noyau Laravel et fige la config) : phpstan-flags.neon est donc inclus avant
// l'extension Larastan dans phpstan.neon / phpstan-modules.neon.
//
// config/auth.php s'en sert pour NE PAS enregistrer le guard/provider dédié
// `travel_customer` pendant l'analyse : Larastan type `$request->user()` et
// `auth()->user()` comme l'union des modèles de TOUS les providers, et ce
// guard grand public ne doit pas polluer le typage des surfaces
// employés/super-admins (ni invalider les messages exacts des baselines
// gelées).
if (! defined('LEOPARDO_STATIC_ANALYSIS')) {
    define('LEOPARDO_STATIC_ANALYSIS', true);
}
