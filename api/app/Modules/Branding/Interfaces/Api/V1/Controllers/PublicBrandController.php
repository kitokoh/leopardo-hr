<?php

declare(strict_types=1);

namespace App\Modules\Branding\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Branding\Domain\Services\BrandResolver;
use App\Modules\Branding\Interfaces\Api\V1\Resources\PublicBrandResource;
use Illuminate\Http\Request;

/**
 * Surface PUBLIQUE d'une Brand (BC-41, #9578).
 *
 * ## 404 uniforme, jamais 403
 *
 * Slug inconnu, Brand non publiée, Brand suspendue, tenant suspendu, host
 * inconnu : **la même réponse 404**. Un 403 confirmerait l'existence de la
 * ressource et permettrait d'énumérer le parc client — c'est l'invariant de
 * `PublicTenantResolver` (#8208) et on ne le réimplémente pas.
 *
 * ## Pourquoi du JSON et pas du HTML
 *
 * BC PAGES (#8730) rend du HTML SSR pour les Pages, parce qu'une Page doit
 * être indexable sans exécuter de JavaScript. Ici, la Brand est un CONTRAT
 * consommé par le front web (qui fait son propre SSR) et par le mobile. Un
 * rendu HTML côté API dupliquerait le travail du front et figerait le design
 * dans le backend.
 */
final class PublicBrandController extends Controller
{
    public function __construct(
        private readonly BrandResolver $resolver,
    ) {}

    /**
     * `GET /api/public/brands/{slug}` — Brand publiée par slug.
     */
    public function show(string $slug): PublicBrandResource
    {
        $resolved = $this->resolver->bySlug($slug);

        if ($resolved === null) {
            abort(404);
        }

        return new PublicBrandResource($resolved['brand']);
    }

    /**
     * `GET /api/public/brands/by-host` — Brand publiée par en-tête `Host`.
     *
     * Le hostname est normalisé (minuscules, sans port) avant la recherche :
     * `Chez-Lea.SN:443` et `chez-lea.sn` désignent le même domaine, et un
     * `Host` forgé avec un port ne doit pas contourner la résolution.
     */
    public function showByHost(Request $request): PublicBrandResource
    {
        $resolved = $this->resolver->byHost($request->getHost());

        if ($resolved === null) {
            abort(404);
        }

        return new PublicBrandResource($resolved['brand']);
    }
}
