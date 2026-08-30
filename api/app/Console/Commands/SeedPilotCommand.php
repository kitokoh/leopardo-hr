<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Seed\PilotSeedGuard;
use Database\Seeders\CrmPilotSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MAT-012 (#5870) — seeds pilotes reproductibles par verticale.
 *
 * Point d'entrée unique pour semer les données pilotes d'une verticale :
 * idempotent (les seeders réentrants skippent l'existant), sans secret ni
 * donnée réelle, et protégé par `PilotSeedGuard` (refus en production sans
 * `--force`, slugs restreints à l'allowlist pilote).
 *
 * Usage :
 *   php artisan pilot:seed crm
 *   php artisan pilot:seed crm --force   (environnement hors pilote/demo)
 */
final class SeedPilotCommand extends Command
{
    protected $signature = 'pilot:seed {vertical : verticale pilote à semer (crm)}
        {--force : autorise l\'exécution hors environnement pilote/demo}';

    protected $description = 'Sème les données pilotes d\'une verticale (idempotent, garde production)';

    /**
     * Registre des verticales → seeder + slugs pilotes ciblés.
     *
     * @var array<string, array{seeder: class-string, slugs: list<string>}>
     */
    private const VERTICALS = [
        'crm' => [
            'seeder' => CrmPilotSeeder::class,
            'slugs' => ['crm-pilot-alpha', 'crm-pilot-beta'],
        ],
    ];

    public function handle(PilotSeedGuard $guard): int
    {
        // Déterministe : les seeds pilotes vivent dans le schéma public (les
        // données tenant sont créées via withinTenant). Ne pas dépendre du
        // search_path ambiant de la session.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        $vertical = (string) $this->argument('vertical');

        $config = self::VERTICALS[$vertical] ?? null;

        if ($config === null) {
            $this->error('Verticale inconnue ['.$vertical.']. Connues : '.implode(', ', array_keys(self::VERTICALS)));

            return self::FAILURE;
        }

        try {
            $guard->assertEnvironment((string) app()->environment(), (bool) $this->option('force'));

            foreach ($config['slugs'] as $slug) {
                $guard->assertPilotSlug($slug);
            }
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => $config['seeder'], '--force' => true]);

        $this->info('Seeds pilotes ['.$vertical.'] appliqués (idempotent).');

        return self::SUCCESS;
    }
}
