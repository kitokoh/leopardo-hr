<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-912 (#6417) — Index des contacts voyageurs : liste tenant-scoped,
 * recherche nom/email, filtre consentement, RBAC rôles gestion, isolation.
 */
class TravelContactsIndexTest extends TestCase
{
    use RefreshTenantDatabase;

    private function login(Company $company, string $role = 'manager', ?string $managerRole = 'principal'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    private function makeContact(Company $company, array $overrides = []): TravelCustomerContact
    {
        return app(TenantManager::class)->withinTenant($company, fn (): TravelCustomerContact => TravelCustomerContact::query()->create(array_merge([
            'company_id' => $company->id,
            'first_name' => 'Jean',
            'last_name' => 'Voyageur',
            'email' => 'jean.voyageur@example.com',
            'email_consent_given' => true,
            'email_consent_at' => now(),
        ], $overrides)));
    }

    public function test_index_lists_contacts_with_search_and_consent_filters(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        $this->makeContact($company);
        $this->makeContact($company, ['first_name' => 'Marie', 'email' => 'marie@example.com', 'email_consent_given' => false]);

        $this->getJson('/api/v1/travel/contacts')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/travel/contacts?search=marie')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'marie@example.com');

        $this->getJson('/api/v1/travel/contacts?consent=email')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'jean.voyageur@example.com');
    }

    public function test_index_requires_management_role(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company, role: 'employee', managerRole: null);

        $this->getJson('/api/v1/travel/contacts')->assertStatus(403);
    }

    public function test_index_is_isolated_per_tenant(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->activateTravel($companyB);

        $this->makeContact($companyA);
        $this->makeContact($companyB, ['email' => 'b@example.com']);

        $this->login($companyB);
        $this->getJson('/api/v1/travel/contacts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'b@example.com');
    }
}
