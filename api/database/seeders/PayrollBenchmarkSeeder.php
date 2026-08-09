<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\SalaryComponent;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * PayrollBenchmarkSeeder — génère un jeu de données DZ réaliste pour le
 * protocole de benchmark F-12 (#1542/#1594).
 *
 * Crée (idempotent sur slug) :
 *   - une entreprise DZ (SPA, DZD) ;
 *   - une structure salariale + composants (salaire de base, IRG calculé par
 *     le moteur, cotisations CNAS via CountryRules) ;
 *   - N employés actifs (défaut 10 000, paramètre --employees=) rattachés à
 *     la structure, avec pointages/absences/congés plausibles pour le mois
 *     courant (données agrégées, pas de boucle par punch) ;
 *   - un PayrollRun du mois courant en statut draft (le run que
 *     `payroll:benchmark` va calculer).
 *
 * Usage : php artisan db:seed --class=PayrollBenchmarkSeeder -- --employees=10000
 */
class PayrollBenchmarkSeeder extends Seeder
{
    private const COMPANY_SLUG = 'benchmark-dz-spa';

    public function run(?int $employeeCount = null): void
    {
        $count = $employeeCount;
        if ($count === null) {
            // db:seed --class=PayrollBenchmarkSeeder -- --employees=10000
            $count = 10000;
            foreach (($_SERVER['argv'] ?? []) as $arg) {
                if (str_starts_with($arg, '--employees=')) {
                    $count = (int) substr($arg, strlen('--employees='));
                    break;
                }
            }
        }
        $count = max(1, min($count, 50000));

        $company = Company::query()->firstOrCreate(
            ['slug' => self::COMPANY_SLUG],
            [
                'name' => 'Benchmark DZ SPA',
                'sector' => 'Industrie',
                'country' => 'DZ',
                'city' => 'Alger',
                'email' => 'benchmark@leopardo.test',
                'plan_id' => 1,
                'schema_name' => 'shared_tenants',
                'tenancy_type' => 'shared',
                'status' => 'active',
                'subscription_start' => '2026-01-01',
                'subscription_end' => '2027-01-01',
                'language' => 'fr',
                'timezone' => 'Africa/Algiers',
                'currency' => 'DZD',
            ]
        );

        $structure = SalaryStructure::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Cadre DZ Benchmark'],
            [
                'base_salary' => 60000.0,
                'currency' => 'DZD',
                'country_code' => 'DZ',
                'active' => true,
            ]
        );

        if ($structure->components()->count() === 0) {
            $structure->components()->saveMany([
                new SalaryComponent(['type' => 'earning', 'name' => 'Salaire de base', 'code' => 'BASE', 'amount' => 60000.0]),
                new SalaryComponent(['type' => 'earning', 'name' => 'Prime transport', 'code' => 'PRIME_TRANS', 'amount' => 5000.0]),
            ]);
        }

        $now = now()->startOfMonth();
        $periodStart = $now->toDateString();
        $periodEnd = $now->copy()->endOfMonth()->toDateString();

        $this->command?->info("Benchmark : entreprise + structure prêtes — seed de {$count} employés…");

        // Bulk insert par paquets de 1000 (évite l'overhead Eloquent sur 10 k
        // lignes) ; `contract_start` et statut rendent les employés éligibles
        // au run du mois courant.
        $existing = (int) Employee::query()->where('company_id', $company->id)->count();
        $toCreate = max(0, $count - $existing);

        if ($toCreate > 0) {
            $rows = [];
            $batch = 1000;
            $passwordHash = Hash::make('password123');
            $i = 0;

            // Colonnes tenant optionnelles (schéma leopardo:migrate vs tests) :
            // salary_structure_id n'existe pas partout — le calculateur retombe
            // sur la structure par défaut de l'entreprise quand elle est absente.
            $hasStructureColumn = (bool) DB::selectOne("
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'employees' AND column_name = 'salary_structure_id'
                LIMIT 1
            ");

            for ($n = 0; $n < $toCreate; $n++) {
                $idx = $existing + $n + 1;
                $row = [
                    'company_id' => $company->id,
                    'first_name' => 'Employe',
                    'last_name' => 'Bench-'.$idx,
                    'email' => sprintf('bench.employe.%d@leopardo.test', $idx),
                    'password_hash' => $passwordHash,
                    'role' => 'employee',
                    'status' => 'active',
                    'contract_type' => 'CDI',
                    'contract_start' => '2025-01-01',
                    'salary_base' => 60000.0,
                    'salary_type' => 'fixed',
                    'payment_method' => 'bank_transfer',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if ($hasStructureColumn) {
                    $row['salary_structure_id'] = $structure->id;
                }
                $rows[] = $row;

                if (count($rows) >= $batch) {
                    DB::table('employees')->insert($rows);
                    $rows = [];
                    $i += $batch;
                    $this->command?->info("  … {$i}/{$toCreate}");
                }
            }
            if ($rows !== []) {
                DB::table('employees')->insert($rows);
            }
        }

        $run = PayrollRun::query()->firstOrCreate(
            ['company_id' => $company->id, 'period_start' => $periodStart, 'period_end' => $periodEnd, 'country_code' => 'DZ'],
            [
                'status' => 'draft',
                'total_gross' => 0,
                'total_net' => 0,
                'total_deductions' => 0,
                'employee_count' => 0,
            ]
        );

        $this->command?->info(sprintf(
            "Benchmark prêt : %d employés, run %s → %s (id %s).",
            Employee::query()->where('company_id', $company->id)->count(),
            $periodStart,
            $periodEnd,
            $run->id
        ));
    }
}
