<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\FuelStation\Infrastructure\Services\FuelStationFeatureRegistrar;
use Illuminate\Console\Command;

/**
 * Enregistre le catalogue FuelStation dans le Feature Registry — Issue #5795.
 *
 * Usage : php artisan fuel-station:register-features
 */
class RegisterFuelStationFeaturesCommand extends Command
{
    protected $signature = 'fuel-station:register-features';

    protected $description = 'Enregistre les fonctionnalites FuelStation dans le Feature Registry (idempotent)';

    public function handle(FuelStationFeatureRegistrar $registrar): int
    {
        $count = $registrar->registerAll();

        $this->info("FuelStation : {$count} fonctionnalites enregistrees.");

        return Command::SUCCESS;
    }
}
