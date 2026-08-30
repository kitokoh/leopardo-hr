<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-913 (#6425) — GET /travel/contacts (liste admin).
 *
 * Registre des contacts voyageurs + consentements horodatés par canal
 * (RGPD) : liste tenant-scoped réservée aux rôles manager, recherche
 * nom/email/téléphone, pagination bornée, aucune fuite inter-tenant.
 */
class TravelContactsAdminApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('travelagency', true);
        $company->save();
        $this->company = $company;
        $this->tenants = app(TenantManager::class);
    }

    private function actingManager(): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function actingEmployee(): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function createContact(array $attributes = []): TravelCustomerContact
    {
        return $this->tenants->withinTenant($this->company, fn (): TravelCustomerContact => TravelCustomerContact::factory()->create($attributes));
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/travel/contacts')->assertStatus(401);
    }

    public function test_index_requires_manager_role(): void
    {
        $this->actingEmployee();

        $this->getJson('/api/v1/travel/contacts')->assertStatus(403);
    }

    public function test_index_lists_tenant_contacts_with_consent_status_and_timestamps(): void
    {
        $this->actingManager();

        $this->createContact([
            'first_name' => 'Aline',
            'last_name' => 'Ngo',
            'email' => 'aline@example.com',
            'email_consent_given' => true,
            'email_consent_at' => now(),
            'sms_consent_given' => true,
            'sms_consent_at' => now(),
        ]);
        $this->createContact(['email' => 'sans-consent@example.com']);

        $response = $this->getJson('/api/v1/travel/contacts')->assertOk();

        $contacts = $response->json('data');
        self::assertCount(2, $contacts);

        $aline = collect($contacts)->firstWhere('email', 'aline@example.com');
        self::assertTrue($aline['email_consent_given']);
        self::assertNotNull($aline['email_consent_at'], 'consentement email horodaté exposé');
        self::assertTrue($aline['sms_consent_given']);
        self::assertNotNull($aline['sms_consent_at']);
        self::assertNull($aline['whatsapp_consent_at']);

        $none = collect($contacts)->firstWhere('email', 'sans-consent@example.com');
        self::assertFalse($none['email_consent_given']);
        self::assertNull($none['email_consent_at']);
    }

    public function test_index_search_filters_by_email_name_and_phone(): void
    {
        $this->actingManager();

        $this->createContact(['first_name' => 'Aline', 'last_name' => 'Ngo', 'email' => 'aline@example.com', 'phone' => '+237699999999']);
        $this->createContact(['first_name' => 'Boris', 'last_name' => 'Tamo', 'email' => 'boris@example.com', 'phone' => '+237688888888']);

        $byEmail = $this->getJson('/api/v1/travel/contacts?search=aline')->assertOk()->json('data');
        self::assertCount(1, $byEmail);
        self::assertSame('aline@example.com', $byEmail[0]['email']);

        $byName = $this->getJson('/api/v1/travel/contacts?search=Tamo')->assertOk()->json('data');
        self::assertCount(1, $byName);
        self::assertSame('boris@example.com', $byName[0]['email']);

        $byPhone = $this->getJson('/api/v1/travel/contacts?search=688888888')->assertOk()->json('data');
        self::assertCount(1, $byPhone);
        self::assertSame('boris@example.com', $byPhone[0]['email']);
    }

    public function test_index_paginates_with_meta(): void
    {
        $this->actingManager();

        foreach (range(1, 5) as $i) {
            $this->createContact(['email' => "pagine{$i}@example.com"]);
        }

        $response = $this->getJson('/api/v1/travel/contacts?per_page=2')->assertOk();

        self::assertCount(2, $response->json('data'));
        self::assertSame(5, $response->json('meta.total'));
        self::assertSame(2, $response->json('meta.per_page'));
        self::assertSame(3, $response->json('meta.last_page'));
    }

    public function test_index_is_isolated_per_tenant(): void
    {
        $this->actingManager();
        $this->createContact(['email' => 'tenanta@example.com']);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $companyB->setFeature('travelagency', true);
        $companyB->save();

        /** @var Employee $managerB */
        $managerB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($managerB);

        $this->tenants->withinTenant($companyB, function (): void {
            TravelCustomerContact::factory()->create(['email' => 'tenantb@example.com']);
        });

        $data = $this->getJson('/api/v1/travel/contacts')->assertOk()->json('data');
        self::assertCount(1, $data);
        self::assertSame('tenantb@example.com', $data[0]['email']);
    }
}
