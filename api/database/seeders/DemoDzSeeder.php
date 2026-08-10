<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\SalaryComponent;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Modules\Payroll\Infrastructure\Services\PayrollClosingService;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Programme FOCUS — F-23 (#1553) / F-06 (#1536) : kit de démo DZ réaliste.
 *
 * Crée (idempotent sur slug) :
 *   - une entreprise DZ (SPA, DZD, Alger) ;
 *   - 3 structures salariales DZ (SMIG, cadre, haut salaire) + composants ;
 *   - N employés actifs (défaut 30, paramètre --employees=) avec des profils
 *     algériens réalistes (noms, matricules, IBAN, contrats CDI) ;
 *   - un type d'absence « Congé payé » ;
 *   - 3 cycles de paie : M-2 et M-1 **clôturés** (calcul → validation RH →
 *     verrouillage comptable via le moteur réel), mois courant calculé
 *     (statut calculated, prêt à la démonstration clôture) ;
 *   - des comptes de démonstration (principal/RH, comptable, employé) —
 *     mot de passe commun `password123` (voir docs/DEMO_KIT_DZ.md).
 *
 * Usage : php artisan db:seed --class=DemoDzSeeder
 *         php artisan db:seed --class=DemoDzSeeder -- --employees=50
 */
class DemoDzSeeder extends Seeder
{
    public const COMPANY_SLUG = 'demo-dz-spa';

    private const DEMO_PASSWORD = 'password123';

    /** Profils algériens réalistes (prénom + nom). */
    private const PROFILES = [
        ['Mohamed', 'Benali'], ['Fatima', 'Meziane'], ['Karim', 'Haddad'],
        ['Yamina', 'Bouzid'], ['Sofiane', 'Cherif'], ['Nadia', 'Kaci'],
        ['Rachid', 'Amrani'], ['Lila', 'Boudiaf'], ['Amine', 'Zerrouki'],
        ['Salima', 'Mansouri'], ['Tarek', 'Belkacem'], ['Samia', 'Guellil'],
        ['Hocine', 'Saidi'], ['Meriem', 'Ait Ahmed'], ['Nabil', 'Ould Ali'],
        ['Wassila', 'Brahimi'], ['Fares', 'Khelifi'], ['Assia', 'Mokrani'],
        ['Slimane', 'Dahmani'], ['Djamila', 'Ferhat'], ['Yacine', 'Bouaziz'],
        ['Zohra', 'Messaoudi'], ['Amar', 'Benseghir'], ['Houria', 'Lounis'],
        ['Lyes', 'Kadri'], ['Rym', 'Touati'], ['Said', 'Bouziane'],
        ['Nora', 'Hammadi'], ['Walid', 'Slimani'], ['Imene', 'Rahmani'],
    ];

    public function run(?int $employeeCount = null): void
    {
        $count = $employeeCount ?? 30;
        if ($employeeCount === null) {
            foreach (($_SERVER['argv'] ?? []) as $arg) {
                if (str_starts_with($arg, '--employees=')) {
                    $count = (int) substr($arg, strlen('--employees='));
                    break;
                }
            }
        }
        $count = max(3, min($count, 200));

        $company = Company::query()->firstOrCreate(
            ['slug' => self::COMPANY_SLUG],
            [
                'name' => 'Leopardo Demo DZ SPA',
                'sector' => 'Services',
                'country' => 'DZ',
                'city' => 'Alger',
                'email' => 'demo-dz@leopardo.test',
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

        $this->seedStructures($company);
        $this->seedAbsenceType($company);

        $manager = $this->seedDemoAccounts($company);

        $existingEmployees = (int) Employee::query()->where('company_id', $company->id)
            ->where('role', 'employee')
            ->whereNotIn('email', [
                'rh.demo-dz@leopardo.test',
                'comptable.demo-dz@leopardo.test',
                'principal.demo-dz@leopardo.test',
                'employe.demo-dz@leopardo.test',
            ])
            ->count();
        $toCreate = max(0, $count - $existingEmployees);
        if ($toCreate > 0) {
            $this->seedEmployees($company, $existingEmployees, $toCreate);
        }

        $this->seedPayrollHistory($company, $manager);

        $this->command?->info(sprintf(
            'DemoDzSeeder : entreprise %s prête (%d employés, comptes demo password123 — voir docs/DEMO_KIT_DZ.md).',
            self::COMPANY_SLUG,
            max($count, $existingEmployees)
        ));
    }

    private function seedStructures(Company $company): void
    {
        $definitions = [
            ['name' => 'SMIG DZ', 'base_salary' => 20000.0],
            ['name' => 'Cadre moyen DZ', 'base_salary' => 60000.0],
            ['name' => 'Cadre supérieur DZ', 'base_salary' => 120000.0],
        ];

        foreach ($definitions as $def) {
            $structure = SalaryStructure::query()->firstOrCreate(
                ['company_id' => $company->id, 'name' => $def['name']],
                [
                    'base_salary' => $def['base_salary'],
                    'currency' => 'DZD',
                    'country_code' => 'DZ',
                    'frequency' => 'monthly',
                    'active' => true,
                ]
            );

            if ($structure->components()->count() === 0) {
                $structure->components()->saveMany([
                    new SalaryComponent(['type' => 'earning', 'name' => 'Salaire de base', 'code' => 'BASE', 'amount' => $def['base_salary']]),
                    new SalaryComponent(['type' => 'earning', 'name' => 'Prime transport', 'code' => 'PRIME_TRANS', 'amount' => 5000.0]),
                ]);
            }
        }
    }

    private function seedAbsenceType(Company $company): void
    {
        AbsenceType::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'PAID_LEAVE'],
            [
                'name' => 'Congé payé',
                'is_paid' => true,
                'deducts_leave' => true,
                'requires_proof' => false,
                'max_days_once' => 30,
            ]
        );
    }

    private function seedDemoAccounts(Company $company): Employee
    {
        $accounts = [
            ['first_name' => 'Karim', 'last_name' => 'Demo RH', 'email' => 'rh.demo-dz@leopardo.test', 'role' => 'manager', 'manager_role' => 'rh'],
            ['first_name' => 'Samia', 'last_name' => 'Demo Compta', 'email' => 'comptable.demo-dz@leopardo.test', 'role' => 'manager', 'manager_role' => 'comptable'],
            ['first_name' => 'Amine', 'last_name' => 'Demo Principal', 'email' => 'principal.demo-dz@leopardo.test', 'role' => 'manager', 'manager_role' => 'principal'],
            ['first_name' => 'Lila', 'last_name' => 'Demo Employe', 'email' => 'employe.demo-dz@leopardo.test', 'role' => 'employee', 'manager_role' => null],
        ];

        $passwordHash = Hash::make(self::DEMO_PASSWORD);
        $manager = null;

        foreach ($accounts as $account) {
            $employee = Employee::query()->firstOrCreate(
                ['company_id' => $company->id, 'email' => $account['email']],
                [
                    'first_name' => $account['first_name'],
                    'last_name' => $account['last_name'],
                    'password_hash' => $passwordHash,
                    'role' => $account['role'],
                    'manager_role' => $account['manager_role'],
                    'status' => 'active',
                    'contract_type' => 'CDI',
                    'contract_start' => '2025-01-01',
                    'salary_base' => 60000.0,
                    'salary_type' => 'fixed',
                    'payment_method' => 'bank_transfer',
                ]
            );

            if ($account['manager_role'] === 'rh') {
                $manager = $employee;
            }
        }

        return $manager ?? Employee::query()->where('company_id', $company->id)->where('role', 'manager')->firstOrFail();
    }

    private function seedEmployees(Company $company, int $offset, int $count): void
    {
        $passwordHash = Hash::make(self::DEMO_PASSWORD);
        $structures = SalaryStructure::query()
            ->where('company_id', $company->id)
            ->where('country_code', 'DZ')
            ->where('active', true)
            ->orderBy('base_salary')
            ->get();

        $hasStructureColumn = (bool) DB::selectOne(
            'SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ? LIMIT 1',
            ['employees', 'salary_structure_id']
        );

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $idx = $offset + $i + 1;
            [$first, $last] = self::PROFILES[$i % count(self::PROFILES)];
            $structure = $structures->get($i % max(1, $structures->count())) ?? $structures->first();

            $row = [
                'company_id' => $company->id,
                'first_name' => $first,
                'last_name' => $last,
                'email' => sprintf('employe.demo-dz.%d@leopardo.test', $idx),
                'password_hash' => $passwordHash,
                'role' => 'employee',
                'status' => 'active',
                'contract_type' => 'CDI',
                'contract_start' => '2025-01-01',
                'salary_base' => $structure?->base_salary ?? 60000.0,
                'salary_type' => 'fixed',
                'payment_method' => 'bank_transfer',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($hasStructureColumn) {
                $row['salary_structure_id'] = $structure?->id;
            }
            $rows[] = $row;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('employees')->insert($chunk);
        }
    }

    /**
     * 3 cycles de paie : M-2 et M-1 clôturés (calcul → validation RH → lock),
     * mois courant calculé (démontre l'étape de clôture).
     */
    private function seedPayrollHistory(Company $company, Employee $manager): void
    {
        $calculator = new PayrollCalculator();
        $closing = new PayrollClosingService();

        $months = [2, 1, 0];
        foreach ($months as $back) {
            $periodStart = now()->startOfMonth()->subMonths($back);
            $periodEnd = $periodStart->copy()->endOfMonth();

            $run = PayrollRun::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                ],
                [
                    'country_code' => 'DZ',
                    'status' => 'draft',
                ]
            );

            // Idempotence (F-23) : un run déjà clôturé (verrouillé) ne doit pas
            // être recalculé — PayrollCalculator refuse les runs verrouillés
            // (F-11) ; on saute le cycle déjà livré.
            if ($run->status === PayrollRun::STATUS_LOCKED) {
                continue;
            }

            $calculator->calculateRun($run);

            if ($back > 0) {
                $closing->validateRh($run->fresh(), $manager);
                $closing->lock($run->fresh(), $manager);
            }
        }
    }
}
