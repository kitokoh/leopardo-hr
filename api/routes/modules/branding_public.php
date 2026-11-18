<?php

declare(strict_types=1);

/**
 * Routes PUBLIQUES du BC BRANDING (BC-41, #9578).
 *
 * Chargé depuis routes/api.php dans un groupe ISOLÉ : aucun middleware
 * `auth`, aucun middleware `tenant`, aucun `token.refresh`. Un visiteur anonyme
 * doit pouvoir lire le contrat d'une Brand — c'est tout l'objet de la surface.
 *
 * URLs réelles (le groupe parent applique déjà le préfixe /v1) :
 *   GET /api/v1/public/brands/{slug}
 *   GET /api/v1/public/brands/by-host
 *
 * `throttle:shop-public` est le limiteur déjà utilisé par les autres surfaces
 * publiques (vitrine, catalogue, restaurant, Pages) : même budget anti-scraping
 * par IP, donc pas de nouveau réglage à calibrer ni de risque d'oublier une
 * limite.
 *
 * Le rendu par `Host` (domaine personnalisé) est résolu par l'en-tête `Host` :
 * c'est le serveur web qui route le domaine vers cette application. Le slug
 * reste disponible en parallèle — un domaine premium AJOUTE une surface
 * d'accès, il n'en retire pas.
 */

use App\Modules\Branding\Interfaces\Api\V1\Controllers\PublicBrandController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:shop-public'])->prefix('public/brands')->group(function (): void {
    // Résolution par Host : DOIT être déclarée AVANT `/{slug}` sinon « by-host »
    // serait capturé comme un slug. Le `where` sur le slug est la seconde
    // garantie, l'ordre reste la plus lisible.
    Route::get('/by-host', [PublicBrandController::class, 'showByHost'])
        ->name('brands.public.by_host');

    Route::get('/{slug}', [PublicBrandController::class, 'show'])
        ->where('slug', '[a-z0-9][a-z0-9\-]{0,158}[a-z0-9]')
        ->name('brands.public.show');
});
