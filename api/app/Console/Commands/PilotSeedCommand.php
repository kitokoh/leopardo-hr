<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use Database\Seeders\Concerns\GuardsPilotSeeding;
use Database\Seeders\EduManagerPilotSeeder;
use Database\Seeders\FuelStationPilotSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * MAT-012 (#5870) — Seeds pilotes par verticale (BC-01 PLATFORM).
 *
 * Crée (ou nettoie) le tenant pilote synthétique d'une solution :
 *   php artisan pilot:seed --solution=fuel          → fuel-pilot-001
 *   php artisan pilot:seed --solution=edu           → edu-pilot-001
 *   php artisan pilot:seed --solution=fuel --clean  → supprime fuel-pilot-001
 *
 * Les seeds sont idempotents (skip si présent), nettoyables et ne peuvent
 * jamais cibler un tenant de production par erreur (garde
 * {@see GuardsPilotSeeding}).
 */
class PilotSeedCommand extends Command
{
    use GuardsPilotSeeding;

    protected $signature = 'pilot:seed
        {--solution=fuel : verticale pilote (fuel|edu)}
        {--clean : supprime le tenant pilote au lieu de le créer}';

    protected $description = 'Crée ou nettoie les seeds pilotes synthétiques par solution (MAT-012 #5870).';

    private const SOLUTIONS = [
        'fuel' => ['class' => FuelStationPilotSeeder::class, 'slug' => FuelStationPilotSeeder::SLUG],
        'edu' => ['class' => EduManagerPilotSeeder::class, 'slug' => EduManagerPilotSeeder::SLUG],
    ];

    public function handle(): int
    {
        $solution = (string) $this->option('solution');

        if (! isset(self::SOLUTIONS[$solution])) {
            $this->error("Solution inconnue '{$solution}' — attendu : fuel|edu.");

            return self::FAILURE;
        }

        $slug = self::SOLUTIONS[$solution]['slug'];

        if ($this->option('clean')) {
            return $this->cleanPilot($slug);
        }

        $this->assertPilotEnvironmentAllowed($solution);

        /** @var \Illuminate\Database\Seeder $seeder */
        $seeder = app(self::SOLUTIONS[$solution]['class']);
        $seeder->setContainer($this->getLaravel());
        $seeder->setCommand($this);
        $seeder->run();

        return self::SUCCESS;
    }

    private function cleanPilot(string $slug): int
    {
        $this->assertPilotEnvironmentAllowed('clean:'.$slug);

        /** @var Company|null $company */
        $company = Company::query()->where('slug', $slug)->first();

        if (! $company instanceof Company) {
            $this->warn("Tenant pilote {$slug} absent — rien à nettoyer.");

            return self::SUCCESS;
        }

        $companyId = (string) $company->id;

        DB::transaction(function () use ($companyId, $company, $slug): void {
            // Suppression des lignes tenant du pilote (toutes les tables connues),
            // puis de la société publique. Les tables absentes sont ignorées.
            foreach ([
                'fuel_sales', 'fuel_cash_session_movements', 'fuel_cash_sessions',
                'fuel_meter_intervals', 'fuel_meter_readings', 'fuel_meter_registers',
                'fuel_shift_assignments', 'fuel_shifts', 'fuel_tanks', 'fuel_pumps',
                'fuel_products', 'fuel_sites', 'fuel_stations',
                'edu_student_guardians', 'edu_students', 'edu_guardians', 'edu_campuses',
            ] as $table) {
                if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                    DB::table($table)->where('company_id', $companyId)->delete();
                }
            }

            DB::table('employees')->where('company_id', $companyId)->delete();
            $company->delete();
        });

        $this->info("Tenant pilote {$slug} supprimé (données synthétiques nettoyées).");

        return self::SUCCESS;
    }
}
