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
                'features' => json_encode([
                    'rh' => true,
                ]),
                'metadata' => json_encode([
                    'legal_id' => $config['legal_id'],
                    'legal_id_label' => $config['legal_label'],
                ]),
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

            foreach ($config['managers'] ?? [] as $managerKey => $managerConfig) {
                $managerIds[$managerKey] = $this->createEmployee(
                    companyId: $companyId,
                    employee: $managerConfig,
                    role: 'manager',
                    managerRole: $managerConfig['manager_role'],
                    departmentId: $departmentIds[$managerConfig['department']],
                    positionId: $positionIds[$managerConfig['position']],
                    scheduleId: $scheduleId,
                    siteId: $siteId,
                    managerId: $managerIds[$managerConfig['reports_to'] ?? 'principal'] ?? $managerIds['principal'],
                );

                $this->createLookup($companyId, $managerConfig['email'], $managerIds[$managerKey], 'manager');
            }

            foreach ($departmentIds as $departmentCode => $departmentId) {
                $managerKey = $config['department_managers'][$departmentCode] ?? ($departmentCode === $config['principal_manager']['department'] ? 'principal' : 'rh');
                $managerId = $managerIds[$managerKey] ?? $managerIds['principal'];

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
                    managerId: $managerIds[$employeeConfig['manager'] ?? 'principal'] ?? $managerIds['principal'],
                );

                $employeeIds[] = $employeeId;
                $this->createLookup($companyId, $employeeConfig['email'], $employeeId, 'employee');
            }

            $allEmployeeIds = array_values($managerIds);
            $allEmployeeIds = array_merge($allEmployeeIds, $employeeIds);
            $activeEmployeeIds = $this->activeEmployeeIds($companyId, $allEmployeeIds);

            $this->seedAttendanceLogs($companyId, $activeEmployeeIds);
            $absenceTypeIds = $this->createAbsenceTypes($companyId);
            $this->seedAbsences($companyId, $employeeIds, $absenceTypeIds, $managerIds['rh']);
            $this->seedLeaveBalanceLogs($companyId, $employeeIds);
            $this->seedSalaryAdvance($companyId, $employeeIds[0] ?? null, $managerIds['rh'], $config['currency']);
            $this->seedPayrolls($companyId, $managerIds['principal'], $activeEmployeeIds, $config['currency']);
            $this->seedProjectsAndTasks($companyId, $managerIds, $activeEmployeeIds, $config['name']);
            $this->seedEvaluations($companyId, $managerIds, $employeeIds);
            $this->seedNotifications($companyId, $allEmployeeIds, $config['name']);
            $this->seedAuditLogs($companyId, $managerIds, $employeeIds);
            $this->seedCompanySettings();
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
            $batchIds = DB::table('payroll_export_batches')
                ->where('company_id', $companyId)
                ->pluck('id');

            if ($batchIds->isNotEmpty()) {
                DB::table('payroll_export_items')->whereIn('batch_id', $batchIds)->delete();
            }

            $tables = [
                'notifications',
                'audit_logs',
                'payroll_export_batches',
                'payrolls',
                'evaluations',
                'task_comments',
                'tasks',
                'projects',
                'salary_advances',
                'leave_balance_logs',
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
            'contract_end' => $employee['contract_end'] ?? null,
            'salary_base' => $employee['salary_base'],
            'salary_type' => $employee['salary_type'] ?? 'fixed',
            'hourly_rate' => $employee['hourly_rate'] ?? null,
            'payment_method' => $employee['payment_method'] ?? 'bank_transfer',
            'leave_balance' => $employee['leave_balance'] ?? 12.5,
            'status' => $employee['status'] ?? 'active',
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

    private function activeEmployeeIds(string $companyId, array $employeeIds): array
    {
        return DB::table('employees')
            ->where('company_id', $companyId)
            ->whereIn('id', $employeeIds)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function createAbsenceTypes(string $companyId): array
    {
        $companyCodeSuffix = Str::upper(Str::substr(str_replace('-', '', $companyId), 0, 8));

        $types = [
            'annual' => ['name' => 'Conge annuel', 'code' => "CA_{$companyCodeSuffix}", 'is_paid' => true, 'deducts_leave' => true, 'requires_proof' => false],
            'sick' => ['name' => 'Conge maladie', 'code' => "CM_{$companyCodeSuffix}", 'is_paid' => true, 'deducts_leave' => false, 'requires_proof' => true],
            'unpaid' => ['name' => 'Absence non payee', 'code' => "ANP_{$companyCodeSuffix}", 'is_paid' => false, 'deducts_leave' => false, 'requires_proof' => false],
        ];

        $ids = [];

        foreach ($types as $key => $type) {
            $ids[$key] = DB::table('absence_types')->insertGetId([
                'company_id' => $companyId,
                'name' => $type['name'],
                'code' => $type['code'],
                'is_paid' => $type['is_paid'],
                'deducts_leave' => $type['deducts_leave'],
                'requires_proof' => $type['requires_proof'],
                'created_at' => now(),
            ]);
        }

        return $ids;
    }

    private function seedAbsences(string $companyId, array $employeeIds, array $absenceTypeIds, int $approvedBy): void
    {
        if (count($employeeIds) < 2) {
            return;
        }

        DB::table('absences')->insert([
            [
                'company_id' => $companyId,
                'employee_id' => $employeeIds[0],
                'absence_type_id' => $absenceTypeIds['annual'],
                'start_date' => now()->addDays(5)->format('Y-m-d'),
                'end_date' => now()->addDays(7)->format('Y-m-d'),
                'days_count' => 3,
                'status' => 'pending',
                'approved_by' => null,
                'reason' => 'Conge familial',
                'rejected_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $companyId,
                'employee_id' => $employeeIds[1],
                'absence_type_id' => $absenceTypeIds['annual'],
                'start_date' => now()->subDays(10)->format('Y-m-d'),
                'end_date' => now()->subDays(8)->format('Y-m-d'),
                'days_count' => 3,
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'reason' => 'Motif personnel',
                'rejected_reason' => null,
                'created_at' => now()->subDays(12),
                'updated_at' => now()->subDays(10),
            ],
            [
                'company_id' => $companyId,
                'employee_id' => $employeeIds[2] ?? $employeeIds[0],
                'absence_type_id' => $absenceTypeIds['sick'],
                'start_date' => now()->subDays(20)->format('Y-m-d'),
                'end_date' => now()->subDays(19)->format('Y-m-d'),
                'days_count' => 2,
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'reason' => 'Arret maladie court',
                'rejected_reason' => null,
                'created_at' => now()->subDays(21),
                'updated_at' => now()->subDays(20),
            ],
            [
                'company_id' => $companyId,
                'employee_id' => $employeeIds[3] ?? $employeeIds[1],
                'absence_type_id' => $absenceTypeIds['unpaid'],
                'start_date' => now()->subDays(3)->format('Y-m-d'),
                'end_date' => now()->subDays(3)->format('Y-m-d'),
                'days_count' => 1,
                'status' => 'rejected',
                'approved_by' => $approvedBy,
                'reason' => 'Absence exceptionnelle',
                'rejected_reason' => 'Justificatif manquant',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(3),
            ],
        ]);
    }

    private function seedLeaveBalanceLogs(string $companyId, array $employeeIds): void
    {
        foreach (array_slice($employeeIds, 0, 4) as $index => $employeeId) {
            DB::table('leave_balance_logs')->insert([
                [
                    'company_id' => $companyId,
                    'employee_id' => $employeeId,
                    'delta' => 2.08,
                    'reason' => 'accrual_monthly',
                    'reference_id' => null,
                    'balance_after' => 10 + ($index * 1.5),
                    'created_at' => now()->subMonths(2),
                ],
                [
                    'company_id' => $companyId,
                    'employee_id' => $employeeId,
                    'delta' => -1.00,
                    'reason' => 'absence_approved',
                    'reference_id' => null,
                    'balance_after' => 9 + ($index * 1.5),
                    'created_at' => now()->subMonth(),
                ],
            ]);
        }
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

    private function seedPayrolls(string $companyId, int $validatedBy, array $employeeIds, string $currency): void
    {
        $gross = match ($currency) {
            'MAD' => 18000.00,
            'TND' => 9500.00,
            default => 180000.00,
        };

        $payrollIds = [];

        foreach (array_slice($employeeIds, 0, 4) as $index => $employeeId) {
            $grossSalary = round($gross * (1 - ($index * 0.08)), 2);
            $overtimeAmount = $index === 0 ? round($grossSalary * 0.04, 2) : 0;
            $advanceDeduction = $index === 1 ? round($grossSalary * 0.05, 2) : 0;
            $absenceDeduction = $index === 2 ? round($grossSalary * 0.03, 2) : 0;
            $irAmount = round($grossSalary * 0.07, 2);
            $netSalary = $grossSalary + $overtimeAmount - $advanceDeduction - $absenceDeduction - $irAmount;

            $payrollIds[] = DB::table('payrolls')->insertGetId([
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'period_month' => (int) now()->subMonth()->format('m'),
                'period_year' => (int) now()->subMonth()->format('Y'),
                'gross_salary' => $grossSalary,
                'overtime_amount' => $overtimeAmount,
                'bonuses' => json_encode($index === 0 ? [['label' => 'Prime performance', 'amount' => round($grossSalary * 0.06, 2)]] : []),
                'deductions' => json_encode($absenceDeduction > 0 ? [['label' => 'Absence non justifiee', 'amount' => $absenceDeduction]] : []),
                'cotisations' => json_encode([['label' => 'CNAS/CNSS', 'amount' => round($grossSalary * 0.09, 2)]]),
                'ir_amount' => $irAmount,
                'advance_deduction' => $advanceDeduction,
                'absence_deduction' => $absenceDeduction,
                'penalty_deduction' => 0,
                'net_salary' => round($netSalary, 2),
                'status' => $index === 3 ? 'draft' : 'validated',
                'validated_by' => $index === 3 ? null : $validatedBy,
                'validated_at' => $index === 3 ? null : now()->subDays(5),
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subDays($index === 3 ? 1 : 5),
            ]);
        }

        if ($payrollIds === []) {
            return;
        }

        $batchId = DB::table('payroll_export_batches')->insertGetId([
            'company_id' => $companyId,
            'period_month' => (int) now()->subMonth()->format('m'),
            'period_year' => (int) now()->subMonth()->format('Y'),
            'bank_format' => 'DZ_GENERIC',
            'file_path' => 'exports/payroll/demo-batch.csv',
            'total_amount' => DB::table('payrolls')->whereIn('id', $payrollIds)->sum('net_salary'),
            'employees_count' => count($payrollIds),
            'exported_by' => $validatedBy,
            'created_at' => now()->subDays(4),
        ]);

        foreach ($payrollIds as $payrollId) {
            DB::table('payroll_export_items')->insert([
                'batch_id' => $batchId,
                'payroll_id' => $payrollId,
                'amount' => DB::table('payrolls')->where('id', $payrollId)->value('net_salary'),
            ]);
        }
    }

    private function seedProjectsAndTasks(string $companyId, array $managerIds, array $employeeIds, string $companyName): void
    {
        if ($employeeIds === []) {
            return;
        }

        $coreMembers = array_values(array_slice($employeeIds, 0, min(4, count($employeeIds))));

        $projectId = DB::table('projects')->insertGetId([
            'company_id' => $companyId,
            'name' => "Projet pilote {$companyName}",
            'description' => 'Projet de demonstration pour tester le module projets/taches.',
            'start_date' => now()->subWeeks(3)->format('Y-m-d'),
            'end_date' => now()->addWeeks(5)->format('Y-m-d'),
            'members' => json_encode($coreMembers),
            'status' => 'active',
            'created_by' => $managerIds['principal'],
            'created_at' => now()->subWeeks(3),
        ]);

        $taskIds = [];
        $taskIds[] = DB::table('tasks')->insertGetId([
            'company_id' => $companyId,
            'title' => 'Configurer les horaires de pointage',
            'description' => 'Verifier les horaires, tolerances et geofencing.',
            'created_by' => $managerIds['rh'],
            'assigned_to' => json_encode(array_slice($coreMembers, 0, 2)),
            'project_id' => $projectId,
            'due_date' => now()->addDays(3),
            'priority' => 'high',
            'status' => 'inprogress',
            'category' => 'ops',
            'checklist' => json_encode([
                ['label' => 'Verifier le site principal', 'done' => true],
                ['label' => 'Valider la tolerance retard', 'done' => false],
            ]),
            'visibility' => 'visible',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDay(),
        ]);
        $taskIds[] = DB::table('tasks')->insertGetId([
            'company_id' => $companyId,
            'title' => 'Cloturer la paie du mois precedent',
            'description' => 'Controler avances et deductions avant validation finale.',
            'created_by' => $managerIds['principal'],
            'assigned_to' => json_encode(array_values(array_filter([$managerIds['comptable'] ?? null, $managerIds['rh']]))),
            'project_id' => $projectId,
            'due_date' => now()->addDays(1),
            'priority' => 'urgent',
            'status' => 'review',
            'category' => 'finance',
            'checklist' => json_encode([
                ['label' => 'Verifier les avances', 'done' => true],
                ['label' => 'Generer le fichier banque', 'done' => false],
            ]),
            'visibility' => 'visible',
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subHours(6),
        ]);
        $taskIds[] = DB::table('tasks')->insertGetId([
            'company_id' => $companyId,
            'title' => 'Onboarding nouveaux employes',
            'description' => 'Preparer badges, contrats et profils applicatifs.',
            'created_by' => $managerIds['rh'],
            'assigned_to' => json_encode(array_values(array_filter([$managerIds['superviseur'] ?? null]))),
            'project_id' => null,
            'due_date' => now()->addDays(10),
            'priority' => 'normal',
            'status' => 'todo',
            'category' => 'hr',
            'checklist' => json_encode([
                ['label' => 'Creer les matricules', 'done' => false],
                ['label' => 'Envoyer les credentials', 'done' => false],
            ]),
            'visibility' => 'visible',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        foreach ($taskIds as $index => $taskId) {
            DB::table('task_comments')->insert([
                'company_id' => $companyId,
                'task_id' => $taskId,
                'author_id' => $index === 1 ? $managerIds['principal'] : $managerIds['rh'],
                'content' => $index === 1 ? 'Merci de verifier avant export banque.' : 'Point de suivi ajoute pour la demo.',
                'created_at' => now()->subDays(1 + $index),
            ]);
        }
    }

    private function seedEvaluations(string $companyId, array $managerIds, array $employeeIds): void
    {
        foreach (array_slice($employeeIds, 0, 3) as $index => $employeeId) {
            DB::table('evaluations')->insert([
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'evaluator_id' => $managerIds['principal'],
                'period' => now()->subQuarter()->format('Y').'-Q'.now()->subQuarter()->quarter,
                'score' => 3.8 + ($index * 0.3),
                'criteria' => json_encode([
                    ['label' => 'Qualite', 'score' => 4 + $index],
                    ['label' => 'Ponctualite', 'score' => 4],
                    ['label' => 'Collaboration', 'score' => 3 + $index],
                ]),
                'strengths' => 'Bonne execution et communication claire.',
                'improvements' => 'Peut encore gagner en anticipation.',
                'overall_comment' => 'Evaluation de demonstration complete pour les tests.',
                'status' => $index === 2 ? 'draft' : 'submitted',
                'acknowledged_at' => $index === 0 ? now()->subDays(12) : null,
                'created_at' => now()->subDays(20),
                'updated_at' => now()->subDays(12),
            ]);
        }
    }

    private function seedNotifications(string $companyId, array $employeeIds, string $companyName): void
    {
        foreach (array_slice($employeeIds, 0, 6) as $index => $employeeId) {
            DB::table('notifications')->insert([
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'type' => $index % 2 === 0 ? 'payroll' : 'task',
                'title' => $index % 2 === 0 ? 'Bulletin disponible' : 'Nouvelle tache assignee',
                'body' => $index % 2 === 0
                    ? "Le bulletin de paie {$companyName} est pret pour consultation."
                    : "Une nouvelle tache de demonstration a ete assignee dans {$companyName}.",
                'data' => json_encode(['demo' => true, 'sequence' => $index + 1]),
                'is_read' => $index % 3 === 0,
                'read_at' => $index % 3 === 0 ? now()->subHours(5) : null,
                'created_at' => now()->subHours(12 - $index),
            ]);
        }
    }

    private function seedAuditLogs(string $companyId, array $managerIds, array $employeeIds): void
    {
        $targets = array_slice($employeeIds, 0, 2);

        foreach ($targets as $index => $employeeId) {
            DB::table('audit_logs')->insert([
                'company_id' => $companyId,
                'employee_id' => $managerIds['rh'],
                'action' => $index === 0 ? 'employee.updated' : 'absence.reviewed',
                'target_type' => 'employee',
                'target_id' => $employeeId,
                'changes' => json_encode($index === 0
                    ? ['status' => ['from' => 'active', 'to' => 'active'], 'phone' => ['from' => null, 'to' => '+000000000']]
                    : ['absence_status' => ['from' => 'pending', 'to' => 'approved']]),
                'ip' => '127.0.0.1',
                'created_at' => now()->subHours(24 - ($index * 3)),
            ]);
        }
    }

    private function seedCompanySettings(): void
    {
        $this->withSharedTenantSearchPath(function (): void {
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
            ], ['key'], ['value', 'value_type', 'updated_at']);
        });
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
                    'finance' => 'Finance',
                    'support' => 'Support',
                ],
                'positions' => [
                    'principal' => ['name' => 'Directeur General', 'department' => 'ops'],
                    'hr' => ['name' => 'Responsable RH', 'department' => 'hr'],
                    'dept_manager' => ['name' => 'Manager Delivery', 'department' => 'ops'],
                    'accountant' => ['name' => 'Comptable', 'department' => 'finance'],
                    'supervisor' => ['name' => 'Superviseur Support', 'department' => 'support'],
                    'staff' => ['name' => 'Developpeur Senior', 'department' => 'ops'],
                    'finance_staff' => ['name' => 'Analyste Financier', 'department' => 'finance'],
                    'support_staff' => ['name' => 'Agent Support', 'department' => 'support'],
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
                'managers' => [
                    'dept' => [
                        'matricule' => 'DZ-EMP-008',
                        'first_name' => 'Samir',
                        'last_name' => 'Boukhalfa',
                        'email' => 'samir.boukhalfa@techcorp-algerie.dz',
                        'department' => 'ops',
                        'position' => 'dept_manager',
                        'manager_role' => 'dept',
                        'reports_to' => 'principal',
                        'salary_base' => 110000.00,
                    ],
                    'comptable' => [
                        'matricule' => 'DZ-EMP-009',
                        'first_name' => 'Lina',
                        'last_name' => 'Haddad',
                        'email' => 'lina.haddad@techcorp-algerie.dz',
                        'department' => 'finance',
                        'position' => 'accountant',
                        'manager_role' => 'comptable',
                        'reports_to' => 'principal',
                        'salary_base' => 98000.00,
                    ],
                    'superviseur' => [
                        'matricule' => 'DZ-EMP-010',
                        'first_name' => 'Nassim',
                        'last_name' => 'Cheriet',
                        'email' => 'nassim.cheriet@techcorp-algerie.dz',
                        'department' => 'support',
                        'position' => 'supervisor',
                        'manager_role' => 'superviseur',
                        'reports_to' => 'dept',
                        'salary_base' => 93000.00,
                    ],
                ],
                'department_managers' => [
                    'ops' => 'dept',
                    'hr' => 'rh',
                    'finance' => 'comptable',
                    'support' => 'superviseur',
                ],
                'employees' => [
                    ['matricule' => 'DZ-EMP-003', 'first_name' => 'Karim', 'last_name' => 'Aouad', 'email' => 'karim.aouad@techcorp-algerie.dz', 'department' => 'ops', 'position' => 'staff', 'manager' => 'dept', 'salary_base' => 95000.00, 'leave_balance' => 18],
                    ['matricule' => 'DZ-EMP-004', 'first_name' => 'Amina', 'last_name' => 'Brahimi', 'email' => 'amina.brahimi@techcorp-algerie.dz', 'department' => 'ops', 'position' => 'staff', 'manager' => 'dept', 'salary_base' => 88000.00, 'contract_type' => 'CDD', 'contract_end' => now()->addMonths(5)->format('Y-m-d')],
                    ['matricule' => 'DZ-EMP-005', 'first_name' => 'Youcef', 'last_name' => 'Kaddour', 'email' => 'youcef.kaddour@techcorp-algerie.dz', 'department' => 'ops', 'position' => 'staff', 'manager' => 'dept', 'salary_base' => 92000.00, 'status' => 'suspended'],
                    ['matricule' => 'DZ-EMP-006', 'first_name' => 'Nadia', 'last_name' => 'Tlemcani', 'email' => 'nadia.tlemcani@techcorp-algerie.dz', 'department' => 'support', 'position' => 'support_staff', 'manager' => 'superviseur', 'salary_base' => 85000.00],
                    ['matricule' => 'DZ-EMP-007', 'first_name' => 'Mehdi', 'last_name' => 'Saadi', 'email' => 'mehdi.saadi@techcorp-algerie.dz', 'department' => 'support', 'position' => 'support_staff', 'manager' => 'superviseur', 'salary_base' => 78000.00, 'status' => 'archived'],
                    ['matricule' => 'DZ-EMP-011', 'first_name' => 'Salma', 'last_name' => 'Bensaid', 'email' => 'salma.bensaid@techcorp-algerie.dz', 'department' => 'finance', 'position' => 'finance_staff', 'manager' => 'comptable', 'salary_base' => 89000.00, 'payment_method' => 'cash'],
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
                    'finance' => 'Finance',
                ],
                'positions' => [
                    'principal' => ['name' => 'Directrice', 'department' => 'ops'],
                    'hr' => ['name' => 'Responsable RH', 'department' => 'hr'],
                    'comptable' => ['name' => 'Controleur financier', 'department' => 'finance'],
                    'staff' => ['name' => 'Preparateur en pharmacie', 'department' => 'ops'],
                    'hr_staff' => ['name' => 'Assistant RH', 'department' => 'hr'],
                    'finance_staff' => ['name' => 'Assistant Comptable', 'department' => 'finance'],
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
                'managers' => [
                    'comptable' => [
                        'matricule' => 'MA-EMP-007',
                        'first_name' => 'Rachid',
                        'last_name' => 'Benjelloun',
                        'email' => 'rachid.benjelloun@pharmaplus.ma',
                        'department' => 'finance',
                        'position' => 'comptable',
                        'manager_role' => 'comptable',
                        'reports_to' => 'principal',
                        'salary_base' => 12500.00,
                    ],
                ],
                'department_managers' => [
                    'ops' => 'principal',
                    'hr' => 'rh',
                    'finance' => 'comptable',
                ],
                'employees' => [
                    ['matricule' => 'MA-EMP-003', 'first_name' => 'Youssef', 'last_name' => 'Bennani', 'email' => 'youssef.bennani@pharmaplus.ma', 'department' => 'ops', 'position' => 'staff', 'manager' => 'principal', 'salary_base' => 7200.00],
                    ['matricule' => 'MA-EMP-004', 'first_name' => 'Meryem', 'last_name' => 'El Fassi', 'email' => 'meryem.elfassi@pharmaplus.ma', 'department' => 'ops', 'position' => 'staff', 'manager' => 'principal', 'salary_base' => 6900.00, 'status' => 'suspended'],
                    ['matricule' => 'MA-EMP-005', 'first_name' => 'Omar', 'last_name' => 'Lahlou', 'email' => 'omar.lahlou@pharmaplus.ma', 'department' => 'ops', 'position' => 'staff', 'manager' => 'principal', 'salary_base' => 7600.00, 'contract_type' => 'Stage', 'contract_end' => now()->addMonths(3)->format('Y-m-d')],
                    ['matricule' => 'MA-EMP-006', 'first_name' => 'Hind', 'last_name' => 'Alaoui', 'email' => 'hind.alaoui@pharmaplus.ma', 'department' => 'finance', 'position' => 'finance_staff', 'manager' => 'comptable', 'salary_base' => 7100.00],
                    ['matricule' => 'MA-EMP-008', 'first_name' => 'Imane', 'last_name' => 'Kadiri', 'email' => 'imane.kadiri@pharmaplus.ma', 'department' => 'hr', 'position' => 'hr_staff', 'manager' => 'rh', 'salary_base' => 6800.00, 'status' => 'archived'],
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
                    'support' => 'Customer Success',
                ],
                'positions' => [
                    'principal' => ['name' => 'CEO', 'department' => 'ops'],
                    'hr' => ['name' => 'People Manager', 'department' => 'hr'],
                    'supervisor' => ['name' => 'Team Lead', 'department' => 'support'],
                    'staff' => ['name' => 'Software Engineer', 'department' => 'ops'],
                    'hr_staff' => ['name' => 'HR Generalist', 'department' => 'hr'],
                    'support_staff' => ['name' => 'Support Specialist', 'department' => 'support'],
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
                'managers' => [
                    'superviseur' => [
                        'matricule' => 'TN-EMP-007',
                        'first_name' => 'Marwen',
                        'last_name' => 'Chakroun',
                        'email' => 'marwen.chakroun@digitalflow.tn',
                        'department' => 'support',
                        'position' => 'supervisor',
                        'manager_role' => 'superviseur',
                        'reports_to' => 'principal',
                        'salary_base' => 6100.00,
                    ],
                ],
                'department_managers' => [
                    'ops' => 'principal',
                    'hr' => 'rh',
                    'support' => 'superviseur',
                ],
                'employees' => [
                    ['matricule' => 'TN-EMP-003', 'first_name' => 'Aziz', 'last_name' => 'Khelifi', 'email' => 'aziz.khelifi@digitalflow.tn', 'department' => 'ops', 'position' => 'staff', 'manager' => 'principal', 'salary_base' => 4200.00],
                    ['matricule' => 'TN-EMP-004', 'first_name' => 'Rania', 'last_name' => 'Ben Amor', 'email' => 'rania.benamor@digitalflow.tn', 'department' => 'ops', 'position' => 'staff', 'manager' => 'principal', 'salary_base' => 4500.00, 'contract_type' => 'Consultant'],
                    ['matricule' => 'TN-EMP-005', 'first_name' => 'Walid', 'last_name' => 'Jaziri', 'email' => 'walid.jaziri@digitalflow.tn', 'department' => 'support', 'position' => 'support_staff', 'manager' => 'superviseur', 'salary_base' => 4300.00],
                    ['matricule' => 'TN-EMP-006', 'first_name' => 'Ines', 'last_name' => 'Gharbi', 'email' => 'ines.gharbi@digitalflow.tn', 'department' => 'support', 'position' => 'support_staff', 'manager' => 'superviseur', 'salary_base' => 4100.00, 'status' => 'suspended'],
                    ['matricule' => 'TN-EMP-008', 'first_name' => 'Hela', 'last_name' => 'Ben Hassen', 'email' => 'hela.benhassen@digitalflow.tn', 'department' => 'hr', 'position' => 'hr_staff', 'manager' => 'rh', 'salary_base' => 3900.00],
                ],
            ],
        ];
    }
}
