<?php

declare(strict_types=1);

namespace App\Support\Routing;

use Illuminate\Support\Facades\Route;

/**
 * #7959 (point 4) — grouper les routes d'un module tenant SANS redéclarer la
 * pile de middlewares.
 *
 * Avant : les 41 fichiers `api/routes/modules/*.php` répétaient, chacun pour
 * soi, la même chaîne littérale
 *
 *     Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh',
 *                        'tenant', 'throttle:api-plan'])->prefix('x')->group(...)
 *
 * Une pile recopiée 41 fois ne se met pas à jour : ajouter un middleware
 * transverse (ou en retirer un) demande de retrouver et corriger 41 fichiers,
 * et l'oubli d'un seul crée une route silencieusement moins protégée que ses
 * voisines — c'est exactement le mode de défaillance que #7959 décrit sous
 * « middlewares redéclarés à la main — fragile ».
 *
 * Désormais : `tenantModule('absences', function () { ... })` et la pile vit
 * ICI, en un seul point. Une garde CI
 * (`check-module-route-middleware.sh`) refuse la réintroduction de la pile
 * littérale dans `routes/modules/`, pour que l'unification ne se re-défasse pas
 * au prochain module ajouté.
 *
 * La pile est identique à celle historiquement recopiée : ce changement ne
 * modifie aucune autorisation, il en supprime 41 copies.
 */
final class ModuleRoutes
{
    /**
     * Middlewares communs à toutes les routes de module tenant.
     *
     * @var list<string>
     */
    public const TENANT_MIDDLEWARES = [
        'throttle:api',
        'auth:sanctum',
        'token.refresh',
        'tenant',
        'throttle:api-plan',
    ];

    /**
     * Déclare un groupe de routes de module tenant.
     *
     * Un préfixe vide est accepté et signifie « pas de préfixe » — deux
     * modules (billing, dashboard) déclarent leurs routes à la racine
     * `v1` ; leur passer `''` plutôt que d'inventer un segment garde leur
     * arborescence d'URL identique.
     *
     * @param  string  $prefix  préfixe d'URL (ex. `absences`), vide si aucun
     * @param  callable():void  $routes  déclaration des routes
     */
    public static function tenantModule(string $prefix, callable $routes): void
    {
        $pending = Route::middleware(self::TENANT_MIDDLEWARES);

        if ($prefix !== '') {
            $pending->prefix($prefix);
        }

        $pending->group($routes);
    }

    /**
     * Variante pour un module qui ajoute des middlewares à la pile commune
     * (ex. un throttle dédié). Les middlewares supplémentaires sont empilés
     * APRÈS la pile commune, jamais à sa place : on ne peut pas « oublier »
     * l'authentification ou le contexte tenant en croyant personnaliser.
     *
     * @param  list<string>  $extra
     * @param  callable():void  $routes
     */
    public static function tenantModuleWith(string $prefix, array $extra, callable $routes): void
    {
        Route::middleware([...self::TENANT_MIDDLEWARES, ...$extra])
            ->prefix($prefix)
            ->group($routes);
    }
}
