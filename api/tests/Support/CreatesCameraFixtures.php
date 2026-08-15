<?php

namespace Tests\Support;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Hash;

/**
 * Fixtures partagées entre tests du module caméras.
 *
 * Crée rapidement une company + un manager Principal (rôle par défaut pour
 * la plupart des scénarios). Nécessite CreatesMvpSchema.
 */
trait CreatesCameraFixtures
{
    /**
     * @param  array<string, mixed>  $features
     */
    protected function createCompanyWithCameras(string $slug = 'alpha', array $features = ['cameras' => true, 'max_cameras' => 4]): Company
    {
        // Slug unique par appel : plusieurs créations dans un même test (ex.
        // CameraRtspSecurityTest::assertHostRejected) violaient companies_slug_unique.
        $slug = $slug.'-'.substr((string) uniqid('', true), -6);

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
        // Email unique par appel (employés_email_unique) — plusieurs managers
        // par test (CameraRtspSecurityTest) violaient la contrainte.
        $email = str_contains($email, '+') ? $email : preg_replace('/@/', '+'.substr((string) uniqid('', true), -6).'@', $email, 1);

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
        $email = str_contains($email, '+') ? $email : preg_replace('/@/', '+'.substr((string) uniqid('', true), -6).'@', $email, 1);

        return Employee::query()->create([
            'company_id' => $company->id,
            'email' => $email,
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function authHeaders(Employee $employee, string $tokenName = 'tests'): array
    {
        $token = $employee->createToken($tokenName)->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }
}
