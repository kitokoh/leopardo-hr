<?php

// #9345 (audit securite 2026-11-10, F3) — `config/session.php` chiffre
// desormais les sessions par defaut (`SESSION_ENCRYPT=true`). Larastan boote
// l'application pour l'analyse statique : sans APP_KEY, le gestionnaire de
// session leve « No application encryption key has been specified » et
// l'analyse echoue AVANT d'avoir produit le moindre diagnostic.
//
// Les jobs PHPStan de la CI n'ont pas de secret a disposition (et ne doivent
// pas en avoir) : on pose une cle FACTICE, uniquement si l'environnement n'en
// fournit pas deja une. Elle ne sert qu'a satisfaire le boot du conteneur
// pendant l'analyse — aucun chiffrement reel n'est effectue ici.
if (getenv('APP_KEY') === false || getenv('APP_KEY') === '') {
    $phpstanDummyAppKey = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';
    putenv('APP_KEY='.$phpstanDummyAppKey);
    $_ENV['APP_KEY'] = $phpstanDummyAppKey;
    $_SERVER['APP_KEY'] = $phpstanDummyAppKey;
}

// Preload the project's Cache contracts to prevent the global
// Larastan install from loading incompatible versions (Laravel 13+).
// The global Larastan bundles newer illuminate contracts that add
// Store::touch() and Repository::touch(), which do not exist in
// Laravel 11 and cause PHP Fatal Errors when the project's
// FileStore/Repository classes are loaded.
foreach (glob(__DIR__.'/vendor/laravel/framework/src/Illuminate/Contracts/Cache/*.php') as $file) {
    require_once $file;
}

// Intercept DocsCommand before Composer's autoloader loads it.
// PHP 8.4 enforces return-type compatibility: configure(): void.
// Laravel 11.51.x DocsCommand::configure() lacks ': void', triggering
// a fatal error that kills the entire PHPStan process.
spl_autoload_register(static function (string $class): bool {
    if ($class === 'Illuminate\\Foundation\\Console\\DocsCommand') {
        require_once __DIR__.'/phpstan-stubs/DocsCommand.php';

        return true;
    }

    return false;
}, true, true);
