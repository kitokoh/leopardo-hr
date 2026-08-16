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
        return Employee::query()->create([
            'email' => $email,
            'password_hash' => Hash::make('password123'),
            'manager_role' => $managerRole,
        ]);
        $employee->company_id = $company->id;
        $employee->role = 'manager';
        $employee->status = 'active';
        $employee->save();

    }

    protected function createEmployee(Company $company, string $email = 'employee@company.test'): Employee
    {
        return Employee::query()->create([
            'email' => $email,
            'password_hash' => Hash::make('password123'),
        ]);
        $employee2->company_id = $company->id;
        $employee2->role = 'employee';
        $employee2->status = 'active';
        $employee2->save();

    }

    /** @return array<string, string> */
    protected function authHeaders(Employee $employee, string $tokenName = 'tests'): array
    {
        $token = $employee->createToken($tokenName)->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }
}

