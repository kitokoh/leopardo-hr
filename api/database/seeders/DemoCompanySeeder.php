<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DemoCompanySeeder
 *
 * Local/demo seeder able to create several complete companies in shared mode:
 * - company
 * - departments / positions / schedule / site
 * - principal manager + HR manager + employees
 * - user_lookups
 * - attendance logs
 * - absences / salary advances / payrolls / settings
 *
 * Usage:
 *   php artisan db:seed --class=DemoCompanySeeder
 */
class DemoCompanySeeder extends Seeder
{
    private const SHARED_SCHEMA = 'shared_tenants';

    public function run(): void
    {
        $allowOutsideLocal = filter_var(env('ALLOW_DEMO_SEEDING', false), FILTER_VALIDATE_BOOLEAN);
        $allowOnce = filter_var(env('DEMO_SEED_ONCE', false), FILTER_VALIDATE_BOOLEAN);

        if (! app()->environment('local', 'development', 'testing') && ! $allowOutsideLocal && ! $allowOnce) {
            $this->command->error('DemoCompanySeeder interdit hors environnement de dev.');
            $this->command->warn('Definir ALLOW_DEMO_SEEDING=true ou DEMO_SEED_ONCE=true pour un seed exceptionnel.');

            return;
        }

        $this->withPublicSearchPath(function (): void {
            DB::statement('CREATE SCHEMA IF NOT EXISTS '.self::SHARED_SCHEMA);
        });

        $plans = $this->planMap();
        if ($plans->isEmpty()) {
            $this->command->error('Plans introuvables. Executer DatabaseSeeder avant DemoCompanySeeder.');

            return;
        }

        $companies = $this->demoCompanies();

        $this->command->info('');
        $this->command->info('Creation des companies de demo...');

        foreach ($companies as $companyConfig) {
            $this->seedCompany($companyConfig, $plans);
        }

        $this->withPublicSearchPath(function (): void {
            DB::statement('SET search_path TO public');
        });

        $this->printSummary($companies);
    }

    private function seedCompany(array $config, Collection $plans): void
    {
        $this->cleanupExistingCompany($config['slug']);

        $companyId = (string) Str::uuid();
        $planId = $plans->get($config['plan']);

        if (! $planId) {
            throw new \RuntimeException("Plan {$config['plan']} introuvable.");
        }

        $this->withPublicSearchPath(function () use ($config, $companyId, $planId): void {
            DB::table('companies')->insert([
                'id' => $companyId,
                'name' => $config['name'],
                'slug' => $config['slug'],
                'sector' => $config['sector'],
                'country' => $config['country'],
                'city' => $config['city'],
                'address' => $config['address'],
                'email' => $config['company_email'],
                'phone' => $config['company_phone'],
                'plan_id' => $planId,
                'schema_name' => self::SHARED_SCHEMA,
                'tenancy_type' => 'shared',
                'status' => $config['status'] ?? 'active',
                'subscription_start' => now()->startOfMonth(),
                'subscription_end' => now()->addMonths(12),
                'language' => $config['language'],
                'timezone' => $config['timezone'],
                'currency' => $config['currency'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $managerIds = [];
        $employeeIds = [];

        $this->withSharedTenantSearchPath(function () use ($config, $companyId, &$managerIds, &$employeeIds): void {
            $departmentIds = $this->createDepartments($companyId, $config['departments']);
            $positionIds = $this->createPositions($companyId, $departmentIds, $config['positions']);
            $scheduleId = $this->createDefaultSchedule($companyId, $config['schedule_name']);
            $siteId = $this->createSite($companyId, $config);

            $managerIds['principal'] = $this->createEmployee(
                companyId: $companyId,
                employee: $config['principal_manager'],
                role: 'manager',
                managerRole: 'principal',
                departmentId: $departmentIds[$config['principal_manager']['department']],
                positionId: $positionIds[$config['principal_manager']['position']],
                scheduleId: $scheduleId,
                siteId: $siteId,
                managerId: null,
            );

            $this->createLookup($companyId, $config['principal_manager']['email'], $managerIds['principal'], 'manager');

            $managerIds['rh'] = $this->createEmployee(
                companyId: $companyId,
                employee: $config['hr_manager'],
                role: 'manager',
                managerRole: 'rh',
                departmentId: $departmentIds[$config['hr_manager']['department']],
                positionId: $positionIds[$config['hr_manager']['position']],
                scheduleId: $scheduleId,
                siteId: $siteId,
                managerId: $managerIds['principal'],
            );

            $this->createLookup($companyId, $config['hr_manager']['email'], $managerIds['rh'], 'manager');

            foreach ($departmentIds as $departmentCode => $departmentId) {
                $managerId = $departmentCode === $config['principal_manager']['department']
                    ? $managerIds['principal']
                    : $managerIds['rh'];

                DB::table('departments')
                    ->where('id', $departmentId)
                    ->update(['manager_id' => $managerId]);
            }

            foreach ($config['employees'] as $employeeConfig) {
                $employeeId = $this->createEmployee(
                    companyId: $companyId,
                    employee: $employeeConfig,
                    role: 'employee',
                    managerRole: null,
                    departmentId: $departmentIds[$employeeConfig['department']],
                    positionId: $positionIds[$employeeConfig['position']],
                    scheduleId: $scheduleId,
                    siteId: $siteId,
                    managerId: $managerIds['principal'],
                );

                $employeeIds[] = $employeeId;
                $this->createLookup($companyId, $employeeConfig['email'], $employeeId, 'employee');
            }

            $allEmployeeIds = array_values($managerIds);
            $allEmployeeIds = array_merge($allEmployeeIds, $employeeIds);

            $this->seedAttendanceLogs($companyId, $allEmployeeIds);
            $absenceTypeId = $this->createAbsenceType($companyId);
            $this->seedAbsences($companyId, $employeeIds, $absenceTypeId, $managerIds['rh']);
            $this->seedSalaryAdvance($companyId, $employeeIds[0] ?? null, $managerIds['rh'], $config['currency']);
            $this->seedPayroll($companyId, $managerIds['principal'], $config['currency']);
            $this->seedCompanySettings($config);
        });
    }

    private function planMap(): Collection
    {
        return $this->withPublicSearchPath(function (): Collection {
            return DB::table('plans')->pluck('id', 'name');
        });
    }

    private function cleanupExistingCompany(string $slug): void
    {
        $company = $this->withPublicSearchPath(function () use ($slug) {
            return DB::table('companies')->where('slug', $slug)->first();
        });

        if (! $company) {
            return;
        }

        $companyId = $company->id;

        $this->withSharedTenantSearchPath(function () use ($companyId): void {
            $tables = [
                'company_settings',
                'payrolls',
                'salary_advances',
                'absences',
                'absence_types',
                'attendance_logs',
                'employees',
                'sites',
                'schedules',
                'positions',
                'departments',
            ];

            foreach ($tables as $table) {
                DB::table($table)->where('company_id', $companyId)->delete();
            }
        });

        $this->withPublicSearchPath(function () use ($companyId): void {
            DB::table('user_lookups')->where('company_id', $companyId)->delete();
            DB::table('companies')->where('id', $companyId)->delete();
        });
    }

    private function createDepartments(string $companyId, array $departments): array
    {
        $ids = [];

        foreach ($departments as $code => $name) {
            $ids[$code] = DB::table('departments')->insertGetId([
                'company_id' => $companyId,
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    private function createPositions(string $companyId, array $departmentIds, array $positions): array
    {
        $ids = [];

        foreach ($positions as $code => $position) {
            $ids[$code] = DB::table('positions')->insertGetId([
                'company_id' => $companyId,
                'name' => $position['name'],
                'department_id' => $departmentIds[$position['department']],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    private function createDefaultSchedule(string $companyId, string $name): int
    {
        return DB::table('schedules')->insertGetId([
            'company_id' => $companyId,
            'name' => $name,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'work_days' => json_encode([1, 2, 3, 4, 5]),
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.00,
            'overtime_threshold_weekly' => 40.00,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSite(string $companyId, array $config): int
    {
        return DB::table('sites')->insertGetId([
            'company_id' => $companyId,
            'name' => $config['site_name'],
            'address' => $config['address'],
            'gps_lat' => $config['gps_lat'],
            'gps_lng' => $config['gps_lng'],
            'gps_radius_m' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createEmployee(
        string $companyId,
        array $employee,
        string $role,
        ?string $managerRole,
        int $departmentId,
        int $positionId,
        int $scheduleId,
        int $siteId,
        ?int $managerId,
    ): int {
        return DB::table('employees')->insertGetId([
            'company_id' => $companyId,
            'matricule' => $employee['matricule'],
            'first_name' => $employee['first_name'],
            'last_name' => $employee['last_name'],
            'email' => $employee['email'],
            'phone' => $employee['phone'] ?? null,
            'password_hash' => Hash::make('password123'),
            'role' => $role,
            'manager_role' => $managerRole,
            'department_id' => $departmentId,
            'position_id' => $positionId,
            'schedule_id' => $scheduleId,
            'site_id' => $siteId,
            'manager_id' => $managerId,
            'contract_type' => $employee['contract_type'] ?? 'CDI',
            'contract_start' => $employee['contract_start'] ?? now()->subYear()->format('Y-m-d'),
            'salary_base' => $employee['salary_base'],
            'salary_type' => 'fixed',
            'leave_balance' => $employee['leave_balance'] ?? 12.5,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createLookup(string $companyId, string $email, int $employeeId, string $role): void
    {
        $this->withPublicSearchPath(function () use ($companyId, $email, $employeeId, $role): void {
            DB::table('user_lookups')->insert([
                'email' => $email,
                'company_id' => $companyId,
                'schema_name' => self::SHARED_SCHEMA,
                'employee_id' => $employeeId,
                'role' => $role,
            ]);
        });
    }

    private function seedAttendanceLogs(string $companyId, array $employeeIds): void
    {
        foreach ($employeeIds as $employeeId) {
            foreach ($this->workingDaysThisMonth() as $index => $day) {
                if ($index % 11 === 0) {
                    continue;
                }

                $isLate = $index % 5 === 0;
                $checkIn = $day->copy()->setTime(8, $isLate ? 25 : 5, 0);
                $checkOut = $day->copy()->setTime(17, $index % 4 === 0 ? 45 : 10, 0);

                DB::table('attendance_logs')->insertOrIgnore([
                    'company_id' => $companyId,
                    'employee_id' => $employeeId,
                    'date' => $day->format('Y-m-d'),
                    'session_number' => 1,
                    'check_in' => $checkIn->toIso8601String(),
                    'check_out' => $checkOut->toIso8601String(),
                    'method' => 'mobile',
                    'status' => $isLate ? 'late' : 'ontime',
                    'hours_worked' => round($checkOut->diffInMinutes($checkIn) / 60, 2),
                    'overtime_hours' => max(0, round(($checkOut->diffInMinutes($checkIn) / 60) - 8, 2)),
                    'late_minutes' => $isLate ? 25 : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function createAbsenceType(string $companyId): int
    {
        return DB::table('absence_types')->insertGetId([
            'company_id' => $companyId,
            'name' => 'Conge annuel',
            'code' => 'CONGE_ANNUEL',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedAbsences(string $companyId, array $employeeIds, int $absenceTypeId, int $approvedBy): void
    {
        if (count($employeeIds) < 2) {
            return;
        }

        DB::table('absences')->insert([
            [
                'company_id' => $companyId,
                'employee_id' => $employeeIds[0],
                'absence_type_id' => $absenceTypeId,
                'start_date' => now()->addDays(5)->format('Y-m-d'),
                'end_date' => now()->addDays(7)->format('Y-m-d'),
                'days_count' => 3,
                'status' => 'pending',
                'reason' => 'Conge familial',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $companyId,
                'employee_id' => $employeeIds[1],
                'absence_type_id' => $absenceTypeId,
                'start_date' => now()->subDays(10)->format('Y-m-d'),
                'end_date' => now()->subDays(8)->format('Y-m-d'),
                'days_count' => 3,
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'reason' => 'Motif personnel',
                'created_at' => now()->subDays(12),
                'updated_at' => now()->subDays(10),
            ],
        ]);
    }

    private function seedSalaryAdvance(string $companyId, ?int $employeeId, int $approvedBy, string $currency): void
    {
        if (! $employeeId) {
            return;
        }

        $amount = match ($currency) {
            'MAD' => 2500.00,
            'TND' => 1800.00,
            default => 30000.00,
        };

        DB::table('salary_advances')->insert([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'amount' => $amount,
            'reason' => 'Avance de confort employee',
            'status' => 'active',
            'approved_by' => $approvedBy,
            'decision_comment' => 'Approuve en demo',
            'repayment_months' => 3,
            'monthly_deduction' => round($amount / 3, 2),
            'amount_remaining' => round(($amount / 3) * 2, 2),
            'repayment_plan' => json_encode([
                ['month' => now()->format('Y-m'), 'amount' => round($amount / 3, 2), 'paid' => true],
                ['month' => now()->addMonth()->format('Y-m'), 'amount' => round($amount / 3, 2), 'paid' => false],
                ['month' => now()->addMonths(2)->format('Y-m'), 'amount' => round($amount / 3, 2), 'paid' => false],
            ]),
            'created_at' => now()->subMonth(),
            'updated_at' => now(),
        ]);
    }

    private function seedPayroll(string $companyId, int $employeeId, string $currency): void
    {
        $gross = match ($currency) {
            'MAD' => 18000.00,
            'TND' => 9500.00,
            default => 180000.00,
        };

        DB::table('payrolls')->insert([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'period_month' => (int) now()->subMonth()->format('m'),
            'period_year' => (int) now()->subMonth()->format('Y'),
            'gross_salary' => $gross,
            'overtime_amount' => 0,
            'bonuses' => json_encode([]),
            'deductions' => json_encode([]),
            'cotisations' => json_encode([]),
            'ir_amount' => 0,
            'advance_deduction' => 0,
            'absence_deduction' => 0,
            'penalty_deduction' => 0,
            'net_salary' => $gross,
            'status' => 'validated',
            'validated_by' => $employeeId,
            'validated_at' => now()->subDays(5),
            'created_at' => now()->subDays(7),
            'updated_at' => now()->subDays(5),
        ]);
    }

    private function seedCompanySettings(array $config): void
    {
        DB::table('company_settings')->upsert([
            [
                'key' => 'onboarding_completed',
                'value' => 'true',
                'value_type' => 'boolean',
                'updated_at' => now(),
            ],
            [
                'key' => 'payroll_day',
                'value' => '25',
                'value_type' => 'integer',
                'updated_at' => now(),
            ],
            [
                'key' => 'legal_id',
                'value' => $config['legal_id'],
                'value_type' => 'string',
                'updated_at' => now(),
            ],
            [
                'key' => 'legal_id_label',
                'value' => $config['legal_label'],
                'value_type' => 'string',
                'updated_at' => now(),
            ],
        ], ['key'], ['value', 'value_type', 'updated_at']);
    }

    private function workingDaysThisMonth(): array
    {
        $days = [];
        $start = now()->startOfMonth();
        $end = now()->subDay();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if ($date->dayOfWeek >= 1 && $date->dayOfWeek <= 5) {
                $days[] = $date->copy();
            }
        }

        return array_slice($days, -20);
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
        if (DB::getDriverName() !== 'pgsql') {
            return $callback();
        }

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
        if (DB::getDriverName() !== 'pgsql') {
            return null;
        }

        $result = DB::selectOne('SHOW search_path');

        return is_object($result) ? (string) $result->search_path : null;
    }

    private function printSummary(array $companies): void
    {
        $this->command->info('');
        $this->command->info('Demo multi-company creee avec succes :');

        foreach ($companies as $company) {
            $this->command->info("  - {$company['name']} ({$company['plan']}, {$company['country']}, shared)");
            $this->command->info("    principal : {$company['principal_manager']['email']} / password123");
            $this->command->info("    RH        : {$company['hr_manager']['email']} / password123");
            $this->command->info("    employe   : {$company['employees'][0]['email']} / password123");
        }
    }

    private function demoCompanies(): array
    {
        return [
            [
                'name' => 'TechCorp Algerie SARL',
                'slug' => 'techcorp-algerie',
                'plan' => 'Starter',
                'sector' => 'Technologie',
                'country' => 'DZ',
                'city' => 'Alger',
                'address' => '12 Rue Didouche Mourad, Alger Centre',
                'company_email' => 'rh@techcorp-algerie.dz',
                'company_phone' => '+213 21 23 45 67',
                'language' => 'fr',
                'timezone' => 'Africa/Algiers',
                'currency' => 'DZD',
                'site_name' => 'Siege Alger',
                'gps_lat' => 36.7529,
                'gps_lng' => 3.0420,
                'schedule_name' => 'Horaire standard Alger',
                'legal_id' => '123456789',
                'legal_label' => 'NIF',
                'departments' => [
                    'ops' => 'Developpement',
                    'hr' => 'Ressources Humaines',
                ],
                'positions' => [
                    'principal' => ['name' => 'Directeur General', 'department' => 'ops'],
                    'hr' => ['name' => 'Responsable RH', 'department' => 'hr'],
                    'staff' => ['name' => 'Developpeur Senior', 'department' => 'ops'],
                ],
                'principal_manager' => [
                    'matricule' => 'DZ-EMP-001',
                    'first_name' => 'Ahmed',
                    'last_name' => 'Benali',
                    'email' => 'ahmed.benali@techcorp-algerie.dz',
                    'phone' => '+213 661 23 45 67',
                    'department' => 'ops',
                    'position' => 'principal',
                    'salary_base' => 180000.00,
                ],
                'hr_manager' => [
                    'matricule' => 'DZ-EMP-002',
                    'first_name' => 'Fatima',
                    'last_name' => 'Meziane',
                    'email' => 'fatima.meziane@techcorp-algerie.dz',
                    'department' => 'hr',
                    'position' => 'hr',
                    'salary_base' => 120000.00,
                ],
                'employees' => [
                    ['matricule' => 'DZ-EMP-003', 'first_name' => 'Karim', 'last_name' => 'Aouad', 'email' => 'karim.aouad@techcorp-algerie.dz', 'department' => 'ops', 'position' => 'staff', 'salary_base' => 95000.00],
                    ['matricule' => 'DZ-EMP-004', 'first_name' => 'Amina', 'last_name' => 'Brahimi', 'email' => 'amina.brahimi@techcorp-algerie.dz', 'department' => 'ops', 'position' => 'staff', 'salary_base' => 88000.00],
                    ['matricule' => 'DZ-EMP-005', 'first_name' => 'Youcef', 'last_name' => 'Kaddour', 'email' => 'youcef.kaddour@techcorp-algerie.dz', 'department' => 'ops', 'position' => 'staff', 'salary_base' => 92000.00],
                    ['matricule' => 'DZ-EMP-006', 'first_name' => 'Nadia', 'last_name' => 'Tlemcani', 'email' => 'nadia.tlemcani@techcorp-algerie.dz', 'department' => 'ops', 'position' => 'staff', 'salary_base' => 85000.00],
                    ['matricule' => 'DZ-EMP-007', 'first_name' => 'Mehdi', 'last_name' => 'Saadi', 'email' => 'mehdi.saadi@techcorp-algerie.dz', 'department' => 'ops', 'position' => 'staff', 'salary_base' => 78000.00],
                ],
            ],
            [
                'name' => 'PharmaPlus Casablanca',
                'slug' => 'pharmaplus-casablanca',
                'plan' => 'Business',
                'sector' => 'Sante',
                'country' => 'MA',
                'city' => 'Casablanca',
                'address' => '44 Boulevard Zerktouni, Casablanca',
                'company_email' => 'contact@pharmaplus.ma',
                'company_phone' => '+212 522 00 11 22',
                'language' => 'fr',
                'timezone' => 'Africa/Casablanca',
                'currency' => 'MAD',
                'site_name' => 'Pharmacie centrale',
                'gps_lat' => 33.5731,
                'gps_lng' => -7.5898,
                'schedule_name' => 'Equipe pharmacie Casablanca',
                'legal_id' => 'MA-ICE-00112233',
                'legal_label' => 'ICE',
                'departments' => [
                    'ops' => 'Exploitation',
                    'hr' => 'Administration RH',
                ],
                'positions' => [
                    'principal' => ['name' => 'Directrice', 'department' => 'ops'],
                    'hr' => ['name' => 'Responsable RH', 'department' => 'hr'],
                    'staff' => ['name' => 'Preparateur en pharmacie', 'department' => 'ops'],
                ],
                'principal_manager' => [
                    'matricule' => 'MA-EMP-001',
                    'first_name' => 'Amina',
                    'last_name' => 'Tahiri',
                    'email' => 'amina.tahiri@pharmaplus.ma',
                    'phone' => '+212 600 11 22 33',
                    'department' => 'ops',
                    'position' => 'principal',
                    'salary_base' => 18000.00,
                ],
                'hr_manager' => [
                    'matricule' => 'MA-EMP-002',
                    'first_name' => 'Sara',
                    'last_name' => 'Mansouri',
                    'email' => 'sara.mansouri@pharmaplus.ma',
                    'department' => 'hr',
                    'position' => 'hr',
                    'salary_base' => 14000.00,
                ],
                'employees' => [
                    ['matricule' => 'MA-EMP-003', 'first_name' => 'Youssef', 'last_name' => 'Bennani', 'email' => 'youssef.bennani@pharmaplus.ma', 'department' => 'ops', 'position' => 'staff', 'salary_base' => 7200.00],
                    ['matricule' => 'MA-EMP-004', 'first_name' => 'Meryem', 'last_name' => 'El Fassi', 'email' => 'meryem.elfassi@pharmaplus.ma', 'department' => 'ops', 'position' => 'staff', 'salary_base' => 6900.00],
                    ['matricule' => 'MA-EMP-005', 'first_name' => 'Omar', 'last_name' => 'Lahlou', 'email' => 'omar.lahlou@pharmaplus.ma', 'department' => 'ops', 'position' => 'staff', 'salary_base' => 7600.00],
                    ['matricule' => 'MA-EMP-006', 'first_name' => 'Hind', 'last_name' => 'Alaoui', 'email' => 'hind.alaoui@pharmaplus.ma', 'department' => 'ops', 'position' => 'staff', 'salary_base' => 7100.00],
                ],
            ],
            [
                'name' => 'DigitalFlow Tunis',
                'slug' => 'digitalflow-tunis',
                'plan' => 'Business',
                'sector' => 'Services numeriques',
                'country' => 'TN',
                'city' => 'Tunis',
                'address' => '18 Avenue Habib Bourguiba, Tunis',
                'company_email' => 'hello@digitalflow.tn',
                'company_phone' => '+216 71 11 22 33',
                'language' => 'fr',
                'timezone' => 'Africa/Tunis',
                'currency' => 'TND',
                'site_name' => 'Hub Tunis',
                'gps_lat' => 36.8065,
                'gps_lng' => 10.1815,
                'schedule_name' => 'Horaire bureau Tunis',
                'legal_id' => 'TN-MF-778899',
                'legal_label' => 'Matricule fiscal',
                'departments' => [
                    'ops' => 'Engineering',
                    'hr' => 'People Ops',
                ],
                'positions' => [
                    'principal' => ['name' => 'CEO', 'department' => 'ops'],
                    'hr' => ['name' => 'People Manager', 'department' => 'hr'],
                    'staff' => ['name' => 'Software Engineer', 'department' => 'ops'],
                ],
                'principal_manager' => [
                    'matricule' => 'TN-EMP-001',
                    'first_name' => 'Sofiane',
                    'last_name' => 'Mrad',
                    'email' => 'sofiane.mrad@digitalflow.tn',
                    'phone' => '+216 22 33 44 55',
                    'department' => 'ops',
                    'position' => 'principal',
                    'salary_base' => 9500.00,
                ],
                'hr_manager' => [
                    'matricule' => 'TN-EMP-002',
                    'first_name' => 'Olfa',
                    'last_name' => 'Trabelsi',
                    'email' => 'olfa.trabelsi@digitalflow.tn',
                    'department' => 'hr',
                    'position' => 'hr',
                    'salary_base' => 6800.00,
                ],
                'employees' => [
                    ['matricule' => 'TN-EMP-003', 'first_name' => 'Aziz', 'last_name' => 'Khelifi', 'email' => 'aziz.khelifi@digitalflow.tn', 'department' => 'ops', 'position' => 'staff', 'salary_base' => 4200.00],
                    ['matricule' => 'TN-EMP-004', 'first_name' => 'Rania', 'last_name' => 'Ben Amor', 'email' => 'rania.benamor@digitalflow.tn', 'department' => 'ops', 'position' => 'staff', 'salary_base' => 4500.00],
                    ['matricule' => 'TN-EMP-005', 'first_name' => 'Walid', 'last_name' => 'Jaziri', 'email' => 'walid.jaziri@digitalflow.tn', 'department' => 'ops', 'position' => 'staff', 'salary_base' => 4300.00],
                    ['matricule' => 'TN-EMP-006', 'first_name' => 'Ines', 'last_name' => 'Gharbi', 'email' => 'ines.gharbi@digitalflow.tn', 'department' => 'ops', 'position' => 'staff', 'salary_base' => 4100.00],
                ],
            ],
        ];
    }
}
