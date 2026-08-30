<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * MAT-012 (#5870) — Seeds pilotes et données synthétiques (BC-01 PLATFORM).
 *
 * Crée le tenant pilote d'une solution verticale (fuel_station, edu…) avec
 * des données 100 % synthétiques et déterministes — aucun secret, aucune
 * donnée réelle. Référence : docs/ops/RUNBOOK_PILOT_FUELSTATION.md
 * (tenant `fuel-pilot-001`).
 *
 * Garanties :
 *  - idempotent (slug stable + verrou `seed_locks`) ;
 *  - nettoyable (`delete()`) ;
 *  - ne peut PAS cibler un tenant de production par erreur : si un tenant
 *    porte le slug pilote SANS être marqué pilote, le seed refuse ; en
 *    environnement production, `--force` est requis.
 */
class PilotTenantSeeder
{
    /** @var array<string, array{slug: string, name: string, sector: string, modules: list<string>}> */
    private const SOLUTIONS = [
        'fuel_station' => [
            'slug' => 'fuel-pilot-001',
            'name' => 'FuelStation Pilote — données synthétiques',
            'sector' => 'Énergie',
            'modules' => ['rh', 'finance', 'attendance', 'fuel_station'],
        ],
        'edu' => [
            'slug' => 'edu-pilot-001',
            'name' => 'EduManager Pilote — données synthétiques',
            'sector' => 'Éducation',
            'modules' => ['rh', 'finance', 'attendance', 'edu'],
        ],
    ];

    public function __construct(
        private readonly bool $force = false,
        private readonly string $environment = 'production',
    ) {}

    public function create(string $solution): Company
    {
        $config = $this->resolveSolution($solution);
        $this->assertNotProduction();

        $slug = $config['slug'];

        /** @var Company|null $existing */
        $existing = Company::query()->where('slug', $slug)->first();

        if ($existing instanceof Company) {
            // Garde anti-cible : on ne touche JAMAIS un tenant existant qui
            // n'est pas explicitement un pilote (données réelles).
            $metadata = is_array($existing->metadata) ? $existing->metadata : [];
            if (($metadata['pilot'] ?? false) !== true) {
                throw new \RuntimeException(
                    "Le slug pilote '{$slug}' existe déjà sans marque pilote — seed refusé (tenant potentiellement réel)."
                );
            }

            return $existing;
        }

        // Verrou d'idempotence (seed_locks, schéma public).
        $lockKey = 'pilot_tenant:'.$slug;
        if ($this->lockExists($lockKey)) {
            /** @var Company|null $locked */
            $locked = Company::query()->where('slug', $slug)->first();
            if ($locked instanceof Company) {
                return $locked;
            }
        }

        /** @var Company $company */
        $company = Company::query()->create([
            'name' => $config['name'],
            'slug' => $slug,
            'sector' => $config['sector'],
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'pilot-'.$solution.'@leopardo.test',
            'phone' => '+213000000000',
            'schema_name' => preg_replace('/[^a-zA-Z0-9_]/', '', $slug) ?: 'pilot_tenant',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'language' => 'fr',
            'timezone' => 'Africa/Algiers',
            'currency' => 'DZD',
            'notes' => 'Tenant pilote synthétique (MAT-012, #5870) — aucune donnée réelle.',
            'features' => collect($config['modules'])->mapWithKeys(fn (string $m): array => [$m => true])->all(),
            'metadata' => ['pilot' => true, 'solution' => $solution, 'synthetic' => true],
        ]);

        $this->writeLock($lockKey);

        return $company;
    }

    public function delete(string $solution): void
    {
        $config = $this->resolveSolution($solution);
        $this->assertNotProduction();

        $slug = $config['slug'];

        /** @var Company|null $company */
        $company = Company::query()->where('slug', $slug)->first();

        if ($company instanceof Company) {
            $metadata = is_array($company->metadata) ? $company->metadata : [];
            if (($metadata['pilot'] ?? false) !== true) {
                throw new \RuntimeException(
                    "Le tenant '{$slug}' n'est pas marqué pilote — suppression refusée."
                );
            }
            $company->delete();
        }

        DB::table('seed_locks')->where('lock_key', 'pilot_tenant:'.$slug)->delete();
    }

    /**
     * @return array{slug: string, name: string, sector: string, modules: list<string>}
     */
    private function resolveSolution(string $solution): array
    {
        if (! isset(self::SOLUTIONS[$solution])) {
            throw new \InvalidArgumentException(
                "Solution inconnue '{$solution}' (attendue : ".implode('|', array_keys(self::SOLUTIONS)).').'
            );
        }

        return self::SOLUTIONS[$solution];
    }

    private function assertNotProduction(): void
    {
        if ($this->environment === 'production' && ! $this->force) {
            throw new \RuntimeException(
                'Seed pilote refusé en production sans --force (données synthétiques interdites par défaut).'
            );
        }
    }

    private function lockExists(string $lockKey): bool
    {
        return DB::table('seed_locks')->where('lock_key', $lockKey)->exists();
    }

    private function writeLock(string $lockKey): void
    {
        DB::table('seed_locks')->updateOrInsert(
            ['lock_key' => $lockKey],
            ['ran_at' => now(), 'updated_at' => now()],
        );
    }
}
