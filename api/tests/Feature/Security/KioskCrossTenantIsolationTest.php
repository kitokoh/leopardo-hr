<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * PA2-API-002 — RBAC multi-tenant proof (kiosk device surface).
 *
 * The kiosk API is authenticated with a per-device X-Kiosk-Token header
 * instead of a Sanctum user session, so it needs its own dedicated
 * cross-tenant proof: a kiosk device registered for Company A must never
 * be able to read or mutate Company B's employees, attendance, or leave
 * data, and Company B's device_code must never accept Company A's token.
 */
class KioskCrossTenantIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_kiosk_token_from_one_tenant_cannot_authenticate_against_another_tenants_device_code(): void
    {
        [$companyA, $managerA] = $this->createCompanyWithManager('Company A', 'a@kiosk-iso.test');
        [$companyB, $managerB] = $this->createCompanyWithManager('Company B', 'b@kiosk-iso.test');

        $kioskA = $this->registerKiosk($managerA, 'Kiosk A');
        $kioskB = $this->registerKiosk($managerB, 'Kiosk B');

        // Company A's sync token must not unlock Company B's device_code.
        $this->withHeader('X-Kiosk-Token', $kioskA['sync_token'])
            ->getJson('/api/v1/kiosks/'.$kioskB['device_code'].'/roster')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'INVALID_KIOSK_TOKEN');

        // And vice versa.
        $this->withHeader('X-Kiosk-Token', $kioskB['sync_token'])
            ->getJson('/api/v1/kiosks/'.$kioskA['device_code'].'/roster')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'INVALID_KIOSK_TOKEN');
    }

    public function test_kiosk_roster_only_lists_employees_of_its_own_company(): void
    {
        [$companyA, $managerA] = $this->createCompanyWithManager('Company A', 'a@kiosk-roster.test');
        [$companyB, $managerB] = $this->createCompanyWithManager('Company B', 'b@kiosk-roster.test');

        $employeeA = $this->createBiometricEmployee($companyA, 'EMP-A-01', 'a-emp@kiosk-roster.test');
        $employeeB = $this->createBiometricEmployee($companyB, 'EMP-B-01', 'b-emp@kiosk-roster.test');

        $kioskA = $this->registerKiosk($managerA, 'Kiosk A');

        $response = $this->withHeader('X-Kiosk-Token', $kioskA['sync_token'])
            ->getJson('/api/v1/kiosks/'.$kioskA['device_code'].'/roster')
            ->assertOk();

        $matricules = collect($response->json('data.employees'))->pluck('matricule')->all();

        $this->assertContains($employeeA->matricule, $matricules, 'Kiosk A roster must include its own tenant employee');
        $this->assertNotContains($employeeB->matricule, $matricules, 'Kiosk A roster must never leak Company B employees');
    }

    public function test_kiosk_punch_cannot_check_in_an_employee_belonging_to_another_tenant(): void
    {
        [$companyA, $managerA] = $this->createCompanyWithManager('Company A', 'a@kiosk-punch.test');
        [$companyB] = $this->createCompanyWithManager('Company B', 'b@kiosk-punch.test');

        // Employee only exists in Company B, identified by matricule EMP-B-01.
        $employeeB = $this->createBiometricEmployee($companyB, 'EMP-B-01', 'b-punch@kiosk-punch.test');

        $kioskA = $this->registerKiosk($managerA, 'Kiosk A');

        $this->withHeader('X-Kiosk-Token', $kioskA['sync_token'])
            ->postJson('/api/v1/kiosks/'.$kioskA['device_code'].'/punch', [
                'identifier' => $employeeB->matricule,
                'action' => 'check_in',
            ])
            ->assertNotFound();

        // Confirm no attendance log leaked into Company B's employee from Company A's kiosk.
        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertDatabaseMissing('attendance_logs', [
            'employee_id' => $employeeB->id,
        ]);
    }

    public function test_kiosk_employee_info_cannot_read_another_tenants_employee_data(): void
    {
        [$companyA, $managerA] = $this->createCompanyWithManager('Company A', 'a@kiosk-info.test');
        [$companyB] = $this->createCompanyWithManager('Company B', 'b@kiosk-info.test');

        $employeeB = $this->createBiometricEmployee($companyB, 'EMP-B-01', 'b-info@kiosk-info.test');

        $kioskA = $this->registerKiosk($managerA, 'Kiosk A');

        $this->withHeader('X-Kiosk-Token', $kioskA['sync_token'])
            ->postJson('/api/v1/kiosks/'.$kioskA['device_code'].'/employee-info', [
                'identifier' => $employeeB->matricule,
            ])
            ->assertNotFound();
    }

    public function test_kiosk_leave_balance_cannot_read_another_tenants_employee_data(): void
    {
        [$companyA, $managerA] = $this->createCompanyWithManager('Company A', 'a@kiosk-leave.test');
        [$companyB] = $this->createCompanyWithManager('Company B', 'b@kiosk-leave.test');

        $employeeB = $this->createBiometricEmployee($companyB, 'EMP-B-01', 'b-leave@kiosk-leave.test');

        $kioskA = $this->registerKiosk($managerA, 'Kiosk A');

        $this->withHeader('X-Kiosk-Token', $kioskA['sync_token'])
            ->postJson('/api/v1/kiosks/'.$kioskA['device_code'].'/leave-balance', [
                'identifier' => $employeeB->matricule,
            ])
            ->assertNotFound();
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function createCompanyWithManager(string $name, string $managerEmail): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name).'-'.\Illuminate\Support\Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => $managerEmail,
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'currency' => 'DZD',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Manager',
            'last_name' => 'Principal',
            'email' => $managerEmail,
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        DB::statement('SET search_path TO public');

        return [$company, $manager];
    }

    private function createBiometricEmployee(Company $company, string $matricule, string $email): Employee
    {
        DB::statement('SET search_path TO shared_tenants,public');

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Karim',
            'last_name' => 'Employe',
            'email' => $email,
            'matricule' => $matricule,
            'zkteco_id' => $matricule,
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'biometric_fingerprint_enabled' => true,
            'biometric_fingerprint_reference_path' => $matricule,
        ]);

        DB::statement('SET search_path TO public');

        return $employee;
    }

    /**
     * @return array{device_code: string, sync_token: string}
     */
    private function registerKiosk(Employee $manager, string $name): array
    {
        $response = $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/kiosks', [
                'name' => $name,
                'biometric_mode' => 'fingerprint',
            ])
            ->assertCreated();

        return [
            'device_code' => $response->json('data.device_code'),
            'sync_token' => $response->json('data.sync_token'),
        ];
    }
}
