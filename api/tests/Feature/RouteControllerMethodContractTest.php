<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Tests\TestCase;

/**
 * Issue #7398 — garde CI « route → méthode de contrôleur ».
 *
 * 27 routes déclaraient une action inexistante (`Controller@méthodeAbsente`) :
 * l'appel levait un 500 `Call to undefined method` et `php artisan route:list`
 * ne le voyait pas (le `uses` d'une route est résolu paresseusement, seule une
 * requête réelle exécute la méthode). Cette garde parcourt le routeur RUNTIME
 * (`Route::getRoutes()`), résout chaque `uses` et échoue dès qu'une classe
 * existe sans la méthode visée (ou qu'une classe est introuvable).
 *
 * Formes couvertes : `App\...\Controller@method` et contrôleur invokable
 * (`App\...\Controller` → `__invoke`). Les routes à closure sont ignorées
 * (aucune méthode à résoudre).
 */
class RouteControllerMethodContractTest extends TestCase
{
    public function test_every_controller_route_target_exists(): void
    {
        $broken = [];
        $checked = 0;

        foreach (app('router')->getRoutes() as $route) {
            if (! $route instanceof RoutingRoute) {
                continue;
            }

            $action = $route->getAction('uses');

            if (! is_string($action) || $action === '') {
                continue; // closure
            }

            if (str_contains($action, '@')) {
                [$class, $method] = explode('@', $action, 2);
            } else {
                $class = $action;
                $method = '__invoke';
            }

            $checked++;

            $target = sprintf('%s %s → %s@%s', implode('|', $route->methods()), $route->uri(), $class, $method);

            if (! class_exists($class)) {
                $broken[] = $target.' (classe introuvable)';

                continue;
            }

            if (! method_exists($class, $method)) {
                $broken[] = $target.' (méthode introuvable)';
            }
        }

        // Garde-fou : le contrat doit couvrir la surface réelle (si le routeur
        // n'expose plus rien, le test ne prouve rien).
        $this->assertGreaterThan(1000, $checked, 'Le contrat route → contrôleur doit couvrir toute la surface routée.');

        $this->assertSame(
            [],
            $broken,
            "Routes pointant vers une action de contrôleur inexistante :\n".implode("\n", $broken),
        );
    }
}
