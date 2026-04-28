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
