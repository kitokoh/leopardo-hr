<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Module EduManager (BC-16, EDU-003/005/007/009/010).
 *
 * Le manifeste de solution et l'activation tenant sont livrés par le lot
 * fondations (EDU-001/002, PR #5974) ; ce provider amorce le module pour la
 * structure validée (Clean Architecture) et la découverte des commandes.
 */
class EduManagerServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void {}
}
