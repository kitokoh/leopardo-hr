<?php

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
