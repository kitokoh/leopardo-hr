<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;

/**
 * Fixtures partagées entre tests du module caméras.
 *
 * Crée rapidement une company + un manager Principal (rôle par défaut pour
 * la plupart des scénarios). Nécessite CreatesMvpSchema.
 */
trait CreatesCameraFixtures
{
    protected function createCompanyWithCameras(string $slug = 'alpha', array $features = ['cameras' => true, 'max_cameras' => 4]): Company
    {
        return Company::query()->create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => $slug.'@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'features' => $features,
        ]);
    }

    protected function createManager(
        Company $company,
        string $managerRole = 'principal',
        string $email = 'manager@company.test'
    ): Employee {
        return Employee::query()->create([
            'company_id' => $company->id,
            'email' => $email,
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);
    }

    protected function createEmployee(Company $company, string $email = 'employee@company.test'): Employee
    {
        return Employee::query()->create([
            'company_id' => $company->id,
            'email' => $email,
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);
    }

    protected function authHeaders(Employee $employee, string $tokenName = 'tests'): array
    {
        $token = $employee->createToken($tokenName)->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }
}
