<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Events\CompanyCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProvisionGuidedTrial
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $email, string $companyName): array
    {
        $slug = Str::slug($companyName);
        if (!$slug) {
            $slug = 'sandbox-' . Str::random(6);
        }

        return DB::transaction(function () use ($email, $companyName, $slug): array {
            $company = Company::query()->create([
                'name' => $companyName,
                'slug' => $slug,
                'sector' => 'Non précisé',
                'country' => 'DZ',
                'city' => 'Non précisé',
                'email' => $email,
                'plan_id' => $this->resolveTrialPlanId(),
                'schema_name' => 'shared_tenants',
                'tenancy_type' => 'shared',
                'status' => 'trial',
                'subscription_start' => now()->toDateString(),
                'subscription_end' => now()->addDays(14)->toDateString(),
                'language' => 'fr',
                'timezone' => 'Africa/Algiers',
                'currency' => 'DZD',
                'metadata' => [
                    'provisioned_by' => 'guided_trial',
                    'is_sandbox' => true,
                ],
            ]);

            if (DB::getDriverName() === 'pgsql') {
                DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');
            }
            $this->tenantManager->setTenant($company);

            try {
                $manager = Employee::query()->create([
                    'company_id' => $company->id,
                    'first_name' => 'Manager',
                    'last_name' => 'Sandbox',
                    'email' => $email,
                    'password_hash' => Hash::make(Str::random(16)),
                    'role' => 'manager',
                    'manager_role' => 'principal',
                    'status' => 'active',
                    'contract_type' => 'CDI',
                    'contract_start' => now()->toDateString(),
                    'salary_type' => 'fixed',
                    'salary_base' => 0,
                    'biometric_face_enabled' => false,
                    'biometric_fingerprint_enabled' => false,
                    'extra_data' => [
                        'job_title' => 'Manager principal',
                        'guided_trial' => true,
                    ],
                ]);

                // Basic Seeding to make it look active
                $this->seedBasicSandboxData($company->id, $manager->id);

            } finally {
                $this->tenantManager->resetToPrevious();
            }

            event(new CompanyCreated($company));

            return [
                'success' => true,
                'company' => $company,
                'manager' => $manager,
            ];
        });
    }

    private function resolveTrialPlanId(): int
    {
        $plan = DB::table('plans')->where('is_active', true)->first();
        if ($plan) {
            return $plan->id;
        }
        
        return DB::table('plans')->insertGetId([
            'name' => 'Sandbox Plan',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'max_employees' => 50,
            'features' => json_encode(['rh' => true, 'tasks' => true, 'attendance' => true, 'mobile_apps' => true]),
            'trial_days' => 14,
            'is_active' => true,
        ]);
    }

    private function seedBasicSandboxData(string $companyId, int $managerId): void
    {
        // 1. Department
        $deptId = DB::table('shared_tenants.departments')->insertGetId([
            'company_id' => $companyId,
            'name' => 'Opérations',
            'manager_id' => $managerId,
            'created_at' => now(),
        ]);

        // 2. Schedule
        $scheduleId = DB::table('shared_tenants.schedules')->insertGetId([
            'company_id' => $companyId,
            'name' => 'Standard 8h-17h',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'work_days' => json_encode([1, 2, 3, 4, 5]),
            'is_default' => true,
            'created_at' => now(),
        ]);

        // 3. Fake Employee
        $empId = DB::table('shared_tenants.employees')->insertGetId([
            'company_id' => $companyId,
            'matricule' => 'EMP-001',
            'first_name' => 'Alice',
            'last_name' => 'Dupont',
            'email' => 'alice@demo.local',
            'password_hash' => Hash::make('password'),
            'role' => 'employee',
            'department_id' => $deptId,
            'schedule_id' => $scheduleId,
            'manager_id' => $managerId,
            'contract_type' => 'CDI',
            'salary_base' => 100000,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('public.user_lookups')->insert([
            'email' => 'alice@demo.local',
            'company_id' => $companyId,
            'schema_name' => 'shared_tenants',
            'employee_id' => $empId,
            'role' => 'employee',
        ]);

        // 4. Attendance log
        DB::table('shared_tenants.attendance_logs')->insert([
            'company_id' => $companyId,
            'employee_id' => $empId,
            'date' => now()->format('Y-m-d'),
            'session_number' => 1,
            'check_in' => now()->setTime(8, 0, 0)->toIso8601String(),
            'method' => 'mobile',
            'status' => 'ontime',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
