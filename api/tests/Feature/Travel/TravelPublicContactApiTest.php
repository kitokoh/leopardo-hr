<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-913 (#6425) — POST /public/travel/contact/{companySlug}.
 *
 * Formulaire de contact PUBLIC (visiteur sans session) : tenant résolu
 * depuis le slug (pattern public/careers), même validation (consentement
 * obligatoire) et même logique que POST /travel/contact — événement
 * `travel.contact.submitted.v1` + registre de consentement.
 */
class TravelPublicContactApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCompany(string $slug = 'travel-pilot-001'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'slug' => $slug,
            'country' => 'CM',
            'currency' => 'XAF',
        ]);
        $company->setFeature('travelagency', true);
        $company->save();

        return $company;
    }

    public function test_public_contact_accepts_visitor_request_with_consent(): void
    {
        $company = $this->makeCompany();

        $this->postJson('/api/v1/public/travel/contact/travel-pilot-001', [
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
            ->where('company_id', $company->id)
            ->where('event_type', 'travel.contact.submitted.v1')
            ->firstOrFail();
        self::assertSame('aline@example.com', $event->payload_redacted['email']);

        $contact = TravelCustomerContact::query()
            ->where('company_id', $company->id)
            ->where('email', 'aline@example.com')
            ->firstOrFail();
        self::assertTrue($contact->email_consent_given, 'consentement email enregistré');
    }

    public function test_public_contact_registers_under_the_slug_company(): void
    {
        $companyA = $this->makeCompany('agence-a');
        $companyB = $this->makeCompany('agence-b');

        $this->postJson('/api/v1/public/travel/contact/agence-b', [
            'email' => 'visiteur@example.com',
            'message' => 'Demande pour agence-b.',
            'consent_email' => true,
        ])->assertStatus(202);

        self::assertSame(
            0,
            TravelCustomerContact::query()->where('company_id', $companyA->id)->count(),
            'aucun contact créé sous l’agence A',
        );
        self::assertSame(
            1,
            TravelCustomerContact::query()->where('company_id', $companyB->id)->count(),
            'contact créé sous l’agence B (slug)',
        );
    }

    public function test_public_contact_requires_consent(): void
    {
        $this->makeCompany();

        $this->postJson('/api/v1/public/travel/contact/travel-pilot-001', [
            'email' => 'sans-consent@example.com',
            'message' => 'Bonjour',
        ])->assertStatus(422);

        self::assertSame(0, TravelOutboxEvent::query()->count(), 'aucun événement sans consentement');
    }

    public function test_public_contact_rejects_unknown_slug(): void
    {
        $this->postJson('/api/v1/public/travel/contact/agence-inexistante', [
            'email' => 'x@example.com',
            'message' => 'Bonjour',
            'consent_email' => true,
        ])->assertStatus(404);
    }

    public function test_public_contact_rejects_suspended_company(): void
    {
        $this->makeCompany();
        Company::query()->where('slug', 'travel-pilot-001')->update(['status' => 'suspended']);

        $this->postJson('/api/v1/public/travel/contact/travel-pilot-001', [
            'email' => 'x@example.com',
            'message' => 'Bonjour',
            'consent_email' => true,
        ])->assertStatus(404);
    }
}
