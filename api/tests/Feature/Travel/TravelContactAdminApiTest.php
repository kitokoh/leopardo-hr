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
 * TRAVEL-913 (#6421) — API admin contacts : liste + consentements.
 *
 * Lecture et gestion réservées aux rôles gestion (principal/rh/manager),
 * isolation tenant stricte, opt-in/opt-out horodaté par canal.
 */
class TravelContactAdminApiTest extends TestCase
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

    public function test_contacts_list_requires_manager_role(): void
    {
        /** @var Employee $agent */
        $agent = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'agent',
            'manager_role' => null,
        ]);
        Sanctum::actingAs($agent);

        $this->getJson('/api/v1/travel/contacts')->assertStatus(403);
    }

    public function test_contacts_list_returns_tenant_contacts_with_consents(): void
    {
        $this->actingManager();
        $contactTimestamp = $this->tenants->withinTenant($this->company, function () {
            $contact = TravelCustomerContact::factory()->withEmailConsent()->create([
                'first_name' => 'Aline',
                'last_name' => 'Ngo',
                'email' => 'aline@example.com',
                'phone' => '+237699999999',
            ]);
            TravelCustomerContact::factory()->create([
                'first_name' => 'Bruno',
                'last_name' => 'Mba',
                'email' => 'bruno@example.com',
            ]);

            return $contact->email_consent_at?->toIso8601String();
        });

        $this->getJson('/api/v1/travel/contacts')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.email', 'bruno@example.com') // tri décroissant par id
            ->assertJsonPath('data.0.email_consent_given', false)
            ->assertJsonPath('data.1.email_consent_given', true)
            ->assertJsonPath('data.1.email_consent_at', $contactTimestamp);
    }

    public function test_contacts_list_searches_by_name_and_email(): void
    {
        $this->actingManager();
        $this->tenants->withinTenant($this->company, function (): void {
            TravelCustomerContact::factory()->create(['first_name' => 'Aline', 'last_name' => 'Ngo', 'email' => 'aline@example.com']);
            TravelCustomerContact::factory()->create(['first_name' => 'Bruno', 'last_name' => 'Mba', 'email' => 'bruno@example.com']);
        });

        $this->getJson('/api/v1/travel/contacts?search=aline')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'aline@example.com');

        $this->getJson('/api/v1/travel/contacts?search=Mba')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.last_name', 'Mba');
    }

    public function test_consent_update_opt_in_and_opt_out_is_timestamped(): void
    {
        $this->actingManager();
        $contact = $this->tenants->withinTenant($this->company, fn (): TravelCustomerContact => TravelCustomerContact::factory()->create());

        // Opt-in sms + whatsapp.
        $this->putJson("/api/v1/travel/contacts/{$contact->id}/consent", [
            'sms_consent_given' => true,
            'whatsapp_consent_given' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.sms_consent_given', true)
            ->assertJsonPath('data.whatsapp_consent_given', true);

        $contact->refresh();
        self::assertNotNull($contact->sms_consent_at, 'opt-in horodaté');
        self::assertNotNull($contact->whatsapp_consent_at, 'opt-in horodaté');

        // Opt-out sms : consentement retiré, horodatage reflète le changement.
        $this->putJson("/api/v1/travel/contacts/{$contact->id}/consent", [
            'sms_consent_given' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.sms_consent_given', false);

        $contact->refresh();
        self::assertFalse($contact->sms_consent_given);
        self::assertNotNull($contact->sms_consent_at, 'opt-out horodaté (traçabilité RGPD)');
    }

    public function test_consent_update_requires_manager_role(): void
    {
        /** @var Employee $agent */
        $agent = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'agent',
            'manager_role' => null,
        ]);
        Sanctum::actingAs($agent);

        $contact = $this->tenants->withinTenant($this->company, fn (): TravelCustomerContact => TravelCustomerContact::factory()->create());

        $this->putJson("/api/v1/travel/contacts/{$contact->id}/consent", [
            'email_consent_given' => true,
        ])->assertStatus(403);
    }

    public function test_consent_update_is_isolated_per_tenant(): void
    {
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $foreignContact = $this->tenants->withinTenant($companyB, fn (): TravelCustomerContact => TravelCustomerContact::factory()->create());

        $this->actingManager();

        $this->putJson("/api/v1/travel/contacts/{$foreignContact->id}/consent", [
            'email_consent_given' => true,
        ])->assertStatus(404);

        $this->getJson('/api/v1/travel/contacts')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
