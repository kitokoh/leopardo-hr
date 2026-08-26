<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * #5577 — Garde CI anti-collision de routes.
 *
 * Deux déclarations de la même route (méthode + URI complète) rendent
 * silencieusement inatteignable l'implémentation enregistrée en second
 * (Laravel ne sert que la première). Famille de bugs déjà constatée :
 * - POST /accounting/documents/{document}/payments déclaré deux fois
 *   (AccountingDocumentController::payments vs AccountingPaymentController::store) ;
 * - PUT /attendance/corrections/{correction}/reject dupliqué (rh.php) ;
 * - bloc day-closures copié-collé dans geo.php.
 *
 * Toute collision (méthode, URI) détectée fait échouer la CI avec la liste
 * des routes fautives — une redéclaration intentionnelle n'existe pas : un
 * alias déprécié a toujours une méthode ou une URI différente.
 */
class RouteCollisionGuardTest extends TestCase
{
    public function test_no_route_is_declared_twice_with_same_method_and_uri(): void
    {
        /** @var array<string, list<string>> $byKey */
        $byKey = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            $key = $this->routeKey($route);
            $signature = $this->routeSignature($route);
            $byKey[$key][] = $signature;
        }

        $collisions = array_filter(
            $byKey,
            static fn (array $signatures): bool => count($signatures) > 1,
        );

        $this->assertSame([], $collisions, 'Collision de routes détectée : '.
            (string) json_encode($collisions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Clé d'unicité : méthodes triées (normalise GET/HEAD) + URI complète.
     */
    private function routeKey(Route $route): string
    {
        $methods = array_values(array_unique($route->methods()));
        sort($methods);

        return implode('|', $methods).' '.$route->uri();
    }

    /**
     * Signature lisible pour le message d'échec (contrôleurs cibles).
     */
    private function routeSignature(Route $route): string
    {
        $action = $route->getActionName();

        return sprintf('%s → %s', $route->uri(), $action);
    }
}
