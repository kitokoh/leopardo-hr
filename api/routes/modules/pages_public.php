<?php

declare(strict_types=1);

/**
 * Routes PUBLIQUES du BC PAGES (#8730).
 *
 * Chargé depuis routes/api.php dans un groupe ISOLÉ : aucun middleware
 * `auth`, aucun middleware `tenant`, aucun `token.refresh`. Un visiteur
 * anonyme doit pouvoir ouvrir une Page — c'est tout l'objet de la surface.
 *
 * `throttle:shop-public` est le limiteur déjà utilisé par les autres surfaces
 * publiques (vitrine, catalogue, restaurant) : même budget anti-scraping par
 * IP, donc pas de nouveau réglage à calibrer ni de risque d'oublier une limite.
 *
 * Le rendu par `Host` (domaine personnalisé) est monté sur `/` : c'est le
 * serveur web qui route le domaine vers cette application, et la Page est
 * résolue par l'en-tête `Host`. Le slug reste disponible en parallèle sur
 * `/p/{slug}` — le design (§6) impose que le domaine premium AJOUTE une
 * surface d'accès, sans retirer la surface de l'écosystème.
 */

use App\Modules\Pages\Interfaces\Api\V1\Controllers\PublicPageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:shop-public'])->group(function (): void {
    Route::get('/p/{slug}', [PublicPageController::class, 'show'])
        ->where('slug', '[a-z0-9][a-z0-9\-]{0,158}[a-z0-9]')
        ->name('pages.public.show');

    Route::get('/pages/by-host', [PublicPageController::class, 'showByHost'])
        ->name('pages.public.by_host');
});
