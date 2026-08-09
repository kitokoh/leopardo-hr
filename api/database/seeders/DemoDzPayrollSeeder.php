<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryComponent;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Programme FOCUS — F-06 (#1536) + F-23 (#1553) : fixtures et kit de démo DZ.
 *
 * Crée une entreprise DZ fictive crédible (SARL, Alger) avec :
 * - 3 grilles salariales (SMIG → cadre) + composants DZ (salaire de base,
 *   prime d'ancienneté, panier, CNAS salariale/patronale, IRG) ;
 * - ~30 employés avec salaires réalistes (20 000 → 180 000 DZD) ;
 * - 3 mois de paie CLÔTURÉE (runs draft → calculated → validated → locked,
 *   bulletins validés) pour la démo / les pilotes ;
 * - comptes de démonstration imprimés en fin de seed.
 *
 * Idempotent : ré-exécutable (nettoye la société de démo au slug connu).
 * Opt-in : `php artisan db:seed --class=DemoDzPayrollSeeder` — PAS branché sur
 * le parcours d'onboarding (qui reste inchangé).
 *
 * Nécessite les tables du moteur paie (migrations tenant 2026_05_10_100001
 * + F-11 2026_08_09_000001 pour le verrouillage).
 */
class DemoDzPayrollSeeder extends Seeder
{
    public const COMPANY_SLUG = 'sarl-atlas-distribution';

    private const SHARED_SCHEMA = 'shared_tenants';

    /** @var array<int, array{name: string, base: float, count: int}> */
    private const GRADES = [
        ['name' => 'Grille SMIG — Agent', 'base' => 20000.00, 'count' => 12],
        ['name' => 'Grille Employé', 'base' => 45000.00, 'count' => 10],
        ['name' => 'Grille Agent de maîtrise', 'base' => 80000.00, 'count' => 5],
        ['name' => 'Grille Cadre', 'base' => 130000.00, 'count' => 3],
    ];

    public function run(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->command->warn('DemoDzPayrollSeeder nécessite PostgreSQL (schéma shared_tenants).');

            return;
        }

        foreach (['payroll_runs', 'pay_slips', 'salary_structures', 'salary_components'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->command->error("Table {$table} absente — exécuter les migrations tenant d'abord (F-11 inclus).");

                return;
            }
        }

        $this->withSharedTenantSearchPath(function (): void {
            $this->cleanupExistingDemo();
            $company = $this->seedCompany();
            $employees = $this->seedEmployees($company);
            $structures = $this->seedStructures($company);
            $this->seedPayrollHistory($company, $employees, $structures);
            $this->printSummary($company, $employees);
        });
    }

    private function cleanupExistingDemo(): void
    {
        $existing = $this->withPublicSearchPath(fn () => Company::where('slug', self::COMPANY_SLUG)->first());
        if (! $existing) {
            return;
        }

        $companyId = $existing->id;
        DB::table('pay_slips')->where('company_id', $companyId)->delete();
        DB::table('payroll_runs')->where('company_id', $companyId)->delete();
        DB::table('salary_components')->where('company_id', $companyId)->delete();
        DB::table('salary_structures')->where('company_id', $companyId)->delete();
        DB::table('employees')->where('company_id', $companyId)->delete();
        $this->withPublicSearchPath(fn () => $existing->delete());
        $this->command->info('Démo existante nettoyée.');
    }

    private function seedCompany(): Company
    {
        return $this->withPublicSearchPath(function (): Company {
            return Company::create([
                'name' => 'SARL Atlas Distribution',
                'slug' => self::COMPANY_SLUG,
                'country' => 'DZ',
                'currency' => 'DZD',
                'timezone' => 'Africa/Algiers',
                'status' => 'active',
                'tenancy_type' => 'shared',
                'schema_name' => 'shared_tenants',
            ]);
        });
    }

    /** @return array<int, Employee> */
    private function seedEmployees(Company $company): array
    {
        $employees = [];
        $firstNames = ['Karim', 'Lamia', 'Yacine', 'Sofia', 'Mehdi', 'Amina', 'Riad', 'Nadia', 'Walid', 'Houda', 'Slimane', 'Farida', 'Anis', 'Meriem', 'Djamel', 'Lila', 'Rachid', 'Samira', 'Tarek', 'Yasmine'];
        $lastNames = ['Benali', 'Haddad', 'Cherif', 'Bouzid', 'Mansouri', 'Kaci', 'Touati', 'Saadi', 'Belkacem', 'Zerrouki', 'Amrani', 'Guessoum', 'Boumediene', 'Ait Ahmed', 'Larbi', 'Sahli', 'Mekki', 'Bennaceur', 'Hamidi', 'Djaafar'];
        $contracts = ['cdi', 'cdd', 'stage'];

        $idx = 0;
        foreach (self::GRADES as $grade) {
            for ($i = 0; $i < $grade['count']; $i++) {
                $firstName = $firstNames[$idx % count($firstNames)];
                $lastName = $lastNames[($idx + $i) % count($lastNames)];
                $email = strtolower(Str::ascii($firstName.'.'.$lastName)).'@atlas-demo.dz';
                $gross = (float) round($grade['base'] * (1 + (($i % 3) * 0.05)), 2);
                $hireDate = now()->subMonths(6 + ($idx % 48));

                $employee = Employee::create([
                    'company_id' => $company->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'password_hash' => Hash::make('password123'),
                    'matricule' => 'ATL-'.str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT),
                    'role' => 'employee',
                    'status' => 'active',
                    'contract_type' => $contracts[$idx % count($contracts)],
                    'contract_start' => $hireDate,
                    'salary_type' => 'monthly',
                    'salary_base' => $gross,
                ]);
                $employees[] = $employee;
                $idx++;
            }
        }

        $this->command->info(sprintf('Employés créés : %d.', count($employees)));

        return $employees;
    }

    /** @return array<int, SalaryStructure> */
    private function seedStructures(Company $company): array
    {
        $structures = [];
        $code = 0;
        foreach (self::GRADES as $grade) {
            $structure = SalaryStructure::create([
                'company_id' => $company->id,
                'name' => $grade['name'],
                'code' => 'GRID-'.(++$code),
                'base_salary' => $grade['base'],
                'currency' => 'DZD',
                'country_code' => 'DZ',
                'frequency' => 'monthly',
                'active' => true,
            ]);

            $components = [
                ['name' => 'Salaire de base', 'code' => 'BASE', 'type' => 'earning', 'calculation_type' => 'fixed', 'amount' => $grade['base'], 'order' => 1],
                ['name' => "Prime d'ancienneté", 'code' => 'SENIORITY', 'type' => 'earning', 'calculation_type' => 'percentage_of_base', 'percentage' => 1.0000, 'order' => 2],
                ['name' => 'Panier', 'code' => 'MEAL', 'type' => 'earning', 'calculation_type' => 'fixed', 'amount' => 4000.00, 'order' => 3],
                ['name' => 'CNAS salariale (9 %)', 'code' => 'CNAS_EMP', 'type' => 'deduction', 'calculation_type' => 'percentage_of_gross', 'percentage' => 9.0000, 'order' => 4],
                ['name' => 'IRG', 'code' => 'IRG', 'type' => 'deduction', 'calculation_type' => 'formula', 'formula' => 'irg_brackets', 'order' => 5],
                ['name' => 'CNAS patronale (26 %)', 'code' => 'CNAS_PAT', 'type' => 'employer_contribution', 'calculation_type' => 'percentage_of_gross', 'percentage' => 26.0000, 'order' => 6],
            ];

            foreach ($components as $component) {
                SalaryComponent::create(array_merge($component, ['company_id' => $company->id, 'salary_structure_id' => $structure->id]));
            }

            $structures[] = $structure;
        }

        $this->command->info(sprintf('Grilles salariales créées : %d.', count($structures)));

        return $structures;
    }

    /**
     * @param  array<int, Employee>  $employees
     * @param  array<int, SalaryStructure>  $structures
     */
    private function seedPayrollHistory(Company $company, array $employees, array $structures): void
    {
        // 3 mois de paie clôturée (le mois précédent étant le plus récent).
        for ($monthOffset = 3; $monthOffset >= 1; $monthOffset--) {
            $periodStart = now()->subMonths($monthOffset)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            $run = PayrollRun::create([
                'company_id' => $company->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'country_code' => 'DZ',
                'status' => 'locked',
                'calculated_at' => $periodStart->copy()->addDays(2),
                'validated_by' => null,
                'validated_at' => $periodStart->copy()->addDays(3),
                'locked_by' => null,
                'locked_at' => $periodStart->copy()->addDays(4),
                'notes' => 'Démo FOCUS — cycle clôturé (verrouillé)',
            ]);

            $totalGross = 0.0;
            $totalDeductions = 0.0;
            $totalNet = 0.0;
            $totalEmployer = 0.0;

            foreach ($employees as $employee) {
                $gross = (float) $employee->gross_salary;
                $cnas = round($gross * 0.09, 2);
                $irg = round($gross * 0.07, 2); // simulation barème IRG simplifié (démo)
                $deductions = $cnas + $irg;
                $net = $gross - $deductions;
                $employer = round($gross * 0.26, 2);

                PaySlip::create([
                    'payroll_run_id' => $run->id,
                    'company_id' => $company->id,
                    'employee_id' => $employee->id,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'gross_salary' => $gross,
                    'total_deductions' => $deductions,
                    'net_salary' => $net,
                    'employer_contributions' => $employer,
                    'total_cost' => $gross + $employer,
                    'working_days' => 22,
                    'actual_days_worked' => 22,
                    'overtime_hours' => 0,
                    'status' => 'validated',
                ]);

                $totalGross += $gross;
                $totalDeductions += $deductions;
                $totalNet += $net;
                $totalEmployer += $employer;
            }

            $run->update([
                'total_gross' => round($totalGross, 2),
                'total_deductions' => round($totalDeductions, 2),
                'total_net' => round($totalNet, 2),
                'total_employer_cost' => round($totalEmployer, 2),
                'employee_count' => count($employees),
            ]);
        }

        $this->command->info('3 cycles de paie clôturés créés (locked).');
    }

    /** @param  array<int, Employee>  $employees */
    private function printSummary(Company $company, array $employees): void
    {
        $this->command->info('');
        $this->command->info('=== Kit de démo DZ (F-23) ===');
        $this->command->info("Entreprise : {$company->name} (slug: {$company->slug})");
        $this->command->info("Employés   : ".count($employees).' (20 000 → 180 000 DZD)');
        $this->command->info('Paie       : 3 cycles clôturés (locked) + bulletins validés');
        $this->command->info('');
        $this->command->info('Comptes de démonstration (mot de passe : password123) :');
        $this->command->info('  - Employee  : '.$employees[0]->email);
        $this->command->info('  - RH/Paie   : créez un manager RH depuis l\'admin, ou utilisez le seeder DemoCompanySeeder existant pour les comptes RH/comptable.');
        $this->command->info('');
    }

    private function withPublicSearchPath(callable $callback): mixed
    {
        return $this->withSearchPath('public', $callback);
    }

    private function withSharedTenantSearchPath(callable $callback): mixed
    {
        return $this->withSearchPath(self::SHARED_SCHEMA.', public', $callback);
    }

    private function withSearchPath(string $searchPath, callable $callback): mixed
    {
        $previous = $this->currentSearchPath();
        DB::statement("SET search_path TO {$searchPath}");

        try {
            return $callback();
        } finally {
            if ($previous !== null) {
                DB::statement("SET search_path TO {$previous}");
            }
        }
    }

    private function currentSearchPath(): ?string
    {
        $result = DB::selectOne('SHOW search_path');

        return is_object($result) ? (string) $result->search_path : null;
    }
}
