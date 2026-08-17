<?php

namespace Tests\Support;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Facades\Hash;

/**
 * Fixtures partagées entre tests du module caméras.
 *
 * Crée rapidement une company + un manager Principal (rôle par défaut pour
 * la plupart des scénarios). Nécessite CreatesMvpSchema.
 */
trait CreatesCameraFixtures
{
    /** @param  array<string, mixed>  $features */
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
        $sensitiveEmployee1 = new Employee([
            'email' => $email,
        ]);
        $sensitiveEmployee1->forceFill(['password_hash' => Hash::make('password123')])->save();
        $sensitiveEmployee1->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ])->save();
        return $sensitiveEmployee1;
    }

    protected function createEmployee(Company $company, string $email = 'employee@company.test'): Employee
    {
        $sensitiveEmployee0 = new Employee([
            'email' => $email,
        ]);
        $sensitiveEmployee0->forceFill(['password_hash' => Hash::make('password123')])->save();
        $sensitiveEmployee0->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();
        return $sensitiveEmployee0;
    }

    /** @return array<string, string> */
    protected function authHeaders(Employee $employee, string $tokenName = 'tests'): array
    {
        $token = $employee->createToken($tokenName)->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }
}

