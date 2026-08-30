<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\PilotTenantSeeder;
use Illuminate\Console\Command;

/**
 * MAT-012 (#5870) — Seed du tenant pilote d'une solution (données 100 %
 * synthétiques, idempotent, nettoyable, protégé production).
 *
 * Usage :
 *   php artisan leopardo:seed-pilot --solution=fuel_station
 *   php artisan leopardo:seed-pilot --solution=edu
 *   php artisan leopardo:seed-pilot --solution=fuel_station --delete
 *   php artisan leopardo:seed-pilot --solution=fuel_station --force   # hors prod requis
 */
class SeedPilotTenantCommand extends Command
{
    protected $signature = 'leopardo:seed-pilot
        {--solution=fuel_station : fuel_station|edu}
        {--delete : Supprime le tenant pilote et son verrou}
        {--force : Autorise en environnement production (déconseillé)}';

    protected $description = 'Crée/supprime le tenant pilote synthétique d\'une solution (MAT-012, #5870).';

    public function handle(): int
    {
        $solution = (string) $this->option('solution');
        $seeder = new PilotTenantSeeder(
            force: (bool) $this->option('force'),
            environment: (string) app()->environment(),
        );

        try {
            if ($this->option('delete')) {
                $seeder->delete($solution);
                $this->info("[leopardo:seed-pilot] tenant pilote '{$solution}' supprimé.");

                return self::SUCCESS;
            }

            $company = $seeder->create($solution);

            $this->info(sprintf(
                '[leopardo:seed-pilot] tenant pilote prêt : %s (%s) — id %s, features %s',
                $company->name,
                $company->slug,
                $company->id,
                implode(',', array_keys(array_filter((array) $company->features))),
            ));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
