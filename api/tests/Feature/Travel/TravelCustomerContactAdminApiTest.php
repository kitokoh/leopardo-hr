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
 * TRAVEL-913 (#6425) — Registre admin des contacts voyageurs.
 *
 * GET /travel/contacts (liste paginée + recherche email/nom/téléphone +
 * filtre par consentement) et PATCH /travel/contacts/{contact}/consent
 * (opt-in / opt-out par canal, horodatage conservé à l'opt-in et non
 * effacé à l'opt-out — RGPD §8.5). Isolation tenant stricte (404 / liste
 * propre), rôles principal/rh/manager requis.
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

    private function actingPlainEmployee(): Employee
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

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/travel/contacts')->assertStatus(401);
    }

    public function test_index_forbidden_for_plain_employee(): void
    {
        $this->actingPlainEmployee();

        $this->getJson('/api/v1/travel/contacts')->assertStatus(403);
    }

    public function test_index_lists_contacts_with_consents_and_pagination(): void
    {
        $this->actingManager();

        $contact = $this->tenants->withinTenant(
            $this->company,
            fn (): TravelCustomerContact => TravelCustomerContact::factory()->withEmailConsent()->create([
                'first_name' => 'Aline',
                'last_name' => 'Ngo',
            ]),
        );

        $this->getJson('/api/v1/travel/contacts')
            ->assertOk()
            ->assertJsonPath('data.0.id', $contact->id)
            ->assertJsonPath('data.0.first_name', 'Aline')
            ->assertJsonPath('data.0.email_consent_given', true)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_searches_by_email_name_or_phone(): void
    {
        $this->actingManager();

        $this->tenants->withinTenant($this->company, function (): void {
            TravelCustomerContact::factory()->create(['email' => 'cible@example.com', 'first_name' => 'Cible', 'phone' => '+237600000001']);
            TravelCustomerContact::factory()->create(['email' => 'autre@example.com', 'first_name' => 'Autre', 'phone' => '+237600000002']);
        });

        $this->getJson('/api/v1/travel/contacts?search=cible@example.com')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'cible@example.com');

        $this->getJson('/api/v1/travel/contacts?search=Autre')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.first_name', 'Autre');

        $this->getJson('/api/v1/travel/contacts?search=600000002')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.phone', '+237600000002');
    }

    public function test_index_filters_by_consent_channel(): void
    {
        $this->actingManager();

        $this->tenants->withinTenant($this->company, function (): void {
            TravelCustomerContact::factory()->withEmailConsent()->create(['email' => 'avec@example.com']);
            TravelCustomerContact::factory()->create(['email' => 'sans@example.com']);
        });

        $this->getJson('/api/v1/travel/contacts?consent=email')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'avec@example.com');
    }

    public function test_index_never_leaks_other_tenant_contacts(): void
    {
        $this->actingManager();

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->tenants->withinTenant($other, function (): void {
            TravelCustomerContact::factory()->create(['email' => 'etranger@example.com']);
        });

        $this->getJson('/api/v1/travel/contacts')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_patch_consent_opt_in_records_timestamp(): void
    {
        $this->actingManager();

        $contact = $this->tenants->withinTenant(
            $this->company,
            fn (): TravelCustomerContact => TravelCustomerContact::factory()->create(),
        );

        $this->patchJson('/api/v1/travel/contacts/'.$contact->id.'/consent', [
            'channel' => 'whatsapp',
            'given' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.channel', 'whatsapp')
            ->assertJsonPath('data.given', true);

        $contact->refresh();
        self::assertTrue($contact->whatsapp_consent_given);
        self::assertNotNull($contact->whatsapp_consent_at);
    }

    public function test_patch_consent_opt_out_keeps_history(): void
    {
        $this->actingManager();

        $contact = $this->tenants->withinTenant(
            $this->company,
            fn (): TravelCustomerContact => TravelCustomerContact::factory()->withEmailConsent()->create(),
        );
        $consentedAt = $contact->email_consent_at;

        $this->patchJson('/api/v1/travel/contacts/'.$contact->id.'/consent', [
            'channel' => 'email',
            'given' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.given', false);

        $contact->refresh();
        self::assertFalse($contact->email_consent_given);
        self::assertSame($consentedAt->toIso8601String(), $contact->email_consent_at?->toIso8601String(), 'opt-out ne détruit pas l\u2019historique RGPD');
    }

    public function test_patch_consent_rejects_unknown_channel(): void
    {
        $this->actingManager();

        $contact = $this->tenants->withinTenant(
            $this->company,
            fn (): TravelCustomerContact => TravelCustomerContact::factory()->create(),
        );

        $this->patchJson('/api/v1/travel/contacts/'.$contact->id.'/consent', [
            'channel' => 'pigeon',
            'given' => true,
        ])->assertStatus(422);
    }

    public function test_patch_consent_cross_tenant_is_404(): void
    {
        $this->actingManager();

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $foreignContact = $this->tenants->withinTenant(
            $other,
            fn (): TravelCustomerContact => TravelCustomerContact::factory()->create(),
        );

        $this->patchJson('/api/v1/travel/contacts/'.$foreignContact->id.'/consent', [
            'channel' => 'email',
            'given' => true,
        ])->assertStatus(404);
    }

    public function test_patch_consent_forbidden_for_plain_employee(): void
    {
        $this->actingPlainEmployee();

        $contact = $this->tenants->withinTenant(
            $this->company,
            fn (): TravelCustomerContact => TravelCustomerContact::factory()->create(),
        );

        $this->patchJson('/api/v1/travel/contacts/'.$contact->id.'/consent', [
            'channel' => 'email',
            'given' => true,
        ])->assertStatus(403);
    }

    public function test_post_bulk_consent_still_works(): void
    {
        $this->actingManager();

        $contact = $this->tenants->withinTenant(
            $this->company,
            fn (): TravelCustomerContact => TravelCustomerContact::factory()->create(),
        );

        $this->postJson('/api/v1/travel/contacts/'.$contact->id.'/consent', [
            'email_consent' => true,
            'sms_consent' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $contact->id);

        $contact->refresh();
        self::assertTrue($contact->email_consent_given);
        self::assertFalse($contact->sms_consent_given);
    }
}
