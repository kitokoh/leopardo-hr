<?php

// Preload the project's Cache Store interface to prevent the global
// Larastan install from loading an incompatible version (Laravel 13+).
require_once __DIR__ . '/vendor/laravel/framework/src/Illuminate/Contracts/Cache/Store.php';
