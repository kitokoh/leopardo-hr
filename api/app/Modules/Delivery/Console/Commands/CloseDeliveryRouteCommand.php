<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Console\Commands;

use App\Modules\Delivery\Application\Jobs\CloseDeliveryRouteJob;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;
use Illuminate\Console\Command;

/**
 * Clôture asynchrone d'une tournée volumineuse (BC-26-D07, issue #6295).
 *
 * L'API `POST /deliveries/routes/{id}/close` reste synchrone (clôtures
 * légères) ; ce job est la voie asynchrone pour les grosses tournées :
 *   php artisan delivery:close-route {route} {company}
 * Idempotent : une tournée déjà close n'est pas recalculée (zéro doublon),
 * retry borné, DLQ en cas d'échec persistant.
 */
final class CloseDeliveryRouteCommand extends Command
{
    protected $signature = 'delivery:close-route {route : ID de la tournee} {company : company_id (uuid)}';

    protected $description = 'Cloture asynchrone d\'une tournee Delivery (idempotente, DLQ sur echec)';

    public function handle(): int
    {
        $routeRaw = $this->argument('route');
        $companyRaw = $this->argument('company');
        $routeId = is_numeric($routeRaw) ? (int) $routeRaw : 0;
        $companyId = is_string($companyRaw) ? $companyRaw : '';

        $route = DeliveryRoute::query()
            ->where('company_id', $companyId)
            ->whereKey($routeId)
            ->first();

        if ($route === null) {
            $this->error(__('delivery.commands.route_not_found', ['route' => $routeId]));

            return self::INVALID;
        }

        CloseDeliveryRouteJob::dispatch($routeId, $companyId);

        $this->info(__('delivery.commands.close_route_planned', ['route' => $routeId]));

        return self::SUCCESS;
    }
}
