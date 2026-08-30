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
 * TRAVEL-416 (#6068) — POST /travel/contact → événement lead CRM.
 *
 * Captation d'une demande : validation stricte (bornes, consentement),
 * événement `travel.contact.submitted.v1` publié, registre de
 * consentement mis à jour, isolation tenant.
 */
class TravelContactApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function actingEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    public function test_contact_requires_authentication(): void
    {
        $this->postJson('/api/v1/travel/contact', [
            'email' => 'client@example.com',
            'message' => 'Bonjour',
            'consent_email' => true,
        ])->assertStatus(401);
    }

    public function test_contact_publishes_event_and_registers_consent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->actingEmployee($company);

        $this->postJson('/api/v1/travel/contact', [
            'first_name' => 'Aline',
            'last_name' => 'Ngo',
            'email' => 'aline@example.com',
            'phone' => '+237699999999',
            'message' => 'Je souhaite des informations sur la ligne Douala-Yaoundé.',
            'consent_email' => true,
        ])
            ->assertStatus(202)
            ->assertJson(['status' => 'received']);

        $event = TravelOutboxEvent::query()
            ->where('event_type', 'travel.contact.submitted.v1')
            ->firstOrFail();
        self::assertSame('aline@example.com', $event->payload_redacted['email']);

        $contact = TravelCustomerContact::query()->where('email', 'aline@example.com')->firstOrFail();
        self::assertTrue($contact->email_consent_given, 'consentement email enregistré');
        self::assertSame($company->id, $contact->company_id);
    }

    public function test_contact_upserts_existing_contact_without_duplicate(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->actingEmployee($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            TravelCustomerContact::factory()->create([
                'email' => 'deja@example.com',
                'email_consent_given' => false,
            ]);
        });

        $this->postJson('/api/v1/travel/contact', [
            'email' => 'deja@example.com',
            'message' => 'Suite de ma demande.',
            'consent_email' => true,
        ])->assertStatus(202);

        self::assertSame(
            1,
            TravelCustomerContact::query()->where('email', 'deja@example.com')->count(),
            'pas de doublon (contrainte unique company+email)',
        );
        self::assertTrue(
            TravelCustomerContact::query()->where('email', 'deja@example.com')->firstOrFail()->email_consent_given
        );
    }

    public function test_contact_rejects_missing_consent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->actingEmployee($company);

        $this->postJson('/api/v1/travel/contact', [
            'email' => 'sans-consent@example.com',
            'message' => 'Bonjour',
        ])->assertStatus(422);

        self::assertSame(0, TravelOutboxEvent::query()->count(), 'aucun événement sans consentement');
    }

    public function test_contact_rejects_out_of_bounds_message(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->actingEmployee($company);

        $this->postJson('/api/v1/travel/contact', [
            'email' => 'long@example.com',
            'message' => str_repeat('a', 2001),
            'consent_email' => true,
        ])->assertStatus(422);
    }

    public function test_contact_is_isolated_per_tenant(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->actingEmployee($companyA);

        app(TenantManager::class)->withinTenant($companyA, function (): void {
            TravelCustomerContact::factory()->create([
                'email' => 'tenanta@example.com',
                'email_consent_given' => true,
                'email_consent_at' => now(),
            ]);
        });

        // Le tenant B ne voit pas les contacts du tenant A (le registre est
        // scindé par company_id — aucun endpoint de lecture ici, on vérifie
        // la contrainte unique par tenant).
        app(TenantManager::class)->withinTenant($companyB, function (): void {
            TravelCustomerContact::factory()->create([
                'email' => 'tenanta@example.com',
                'email_consent_given' => true,
                'email_consent_at' => now(),
            ]);
            self::assertSame(1, TravelCustomerContact::query()->count());
        });
    }
}
