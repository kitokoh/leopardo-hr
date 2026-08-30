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
 * TRAVEL-913/914 (#6426/#6427) — Registre des contacts voyageurs : liste
 * admin (avec consentements par canal) et mise à jour du consentement
 * (opt-in/opt-out horodaté, RGPD — registre #6067).
 */
class TravelCustomerContactAdminApiTest extends TestCase
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

    private function actingAgent(): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'agent',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    public function test_index_requires_manager_role(): void
    {
        $this->actingAgent();

        $this->getJson('/api/v1/travel/contacts')
            ->assertStatus(403);
    }

    public function test_index_lists_only_tenant_contacts_with_consents(): void
    {
        $this->actingManager();

        $contact = $this->tenants->withinTenant($this->company, function (): TravelCustomerContact {
            return TravelCustomerContact::factory()->withEmailConsent()->create([
                'first_name' => 'Aline',
                'last_name' => 'Ngo',
                'email' => 'aline@example.com',
            ]);
        });

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->tenants->withinTenant($other, function (): void {
            TravelCustomerContact::factory()->create(['email' => 'autre-tenant@example.com']);
        });

        $this->getJson('/api/v1/travel/contacts')
            ->assertOk()
            ->assertJsonPath('data.0.id', $contact->id)
            ->assertJsonPath('data.0.email', 'aline@example.com')
            ->assertJsonPath('data.0.email_consent_given', true)
            ->assertJsonPath('data.0.email_consent_at', $contact->email_consent_at?->toIso8601String())
            ->assertJsonMissing(['email' => 'autre-tenant@example.com']);
    }

    public function test_index_filters_by_search(): void
    {
        $this->actingManager();

        $this->tenants->withinTenant($this->company, function (): void {
            TravelCustomerContact::factory()->create(['email' => 'cible@example.com']);
            TravelCustomerContact::factory()->create(['email' => 'autre@example.com']);
        });

        $this->getJson('/api/v1/travel/contacts?search=cible')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'cible@example.com');
    }

    public function test_update_consent_opt_in_is_timestamped(): void
    {
        $this->actingManager();

        $contact = $this->tenants->withinTenant($this->company, function (): TravelCustomerContact {
            return TravelCustomerContact::factory()->create();
        });

        $this->patchJson("/api/v1/travel/contacts/{$contact->id}", [
            'channel' => 'whatsapp',
            'consent' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.whatsapp_consent_given', true);

        $contact->refresh();
        self::assertTrue($contact->whatsapp_consent_given);
        self::assertNotNull($contact->whatsapp_consent_at, 'opt-in horodaté');
    }

    public function test_update_consent_opt_out_clears_timestamp(): void
    {
        $this->actingManager();

        $contact = $this->tenants->withinTenant($this->company, function (): TravelCustomerContact {
            return TravelCustomerContact::factory()->withEmailConsent()->create();
        });
        self::assertNotNull($contact->email_consent_at);

        $this->patchJson("/api/v1/travel/contacts/{$contact->id}", [
            'channel' => 'email',
            'consent' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.email_consent_given', false)
            ->assertJsonPath('data.email_consent_at', null);
    }

    public function test_update_consent_rejects_unknown_channel(): void
    {
        $this->actingManager();

        $contact = $this->tenants->withinTenant($this->company, function (): TravelCustomerContact {
            return TravelCustomerContact::factory()->create();
        });

        $this->patchJson("/api/v1/travel/contacts/{$contact->id}", [
            'channel' => 'carrier_pigeon',
            'consent' => true,
        ])->assertStatus(422);
    }

    public function test_update_consent_404_for_other_tenant(): void
    {
        $this->actingManager();

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $foreign = $this->tenants->withinTenant($other, function (): TravelCustomerContact {
            return TravelCustomerContact::factory()->create();
        });

        $this->patchJson("/api/v1/travel/contacts/{$foreign->id}", [
            'channel' => 'email',
            'consent' => true,
        ])->assertStatus(404);
    }
}
