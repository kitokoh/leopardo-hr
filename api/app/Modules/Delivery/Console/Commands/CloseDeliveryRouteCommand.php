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
    protected $signature = 'delivery:close-route {route : ID de la tournée} {company : company_id (uuid)}';

    protected $description = 'Clôture asynchrone d\'une tournée Delivery (idempotente, DLQ sur échec)';

    public function handle(): int
    {
        $routeId = (int) $this->argument('route');
        $companyId = (string) $this->argument('company');

        $route = DeliveryRoute::query()
            ->where('company_id', $companyId)
            ->whereKey($routeId)
            ->first();

        if ($route === null) {
            $this->error("Tournée #{$routeId} introuvable pour ce tenant.");

            return self::INVALID;
        }

        CloseDeliveryRouteJob::dispatch($routeId, $companyId);

        $this->info("Clôture de la tournée #{$routeId} planifiée (job asynchrone).");

        return self::SUCCESS;
    }
}
