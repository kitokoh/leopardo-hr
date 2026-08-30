<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-913 (#6425) — Formulaire de contact PUBLIC + génération du lien.
 *
 * - POST /travel/public/contact : route publique protégée par le middleware
 *   `signed` (company_id paramètre signé) — consentement obligatoire, même
 *   logique que POST /travel/contact.
 * - POST /travel/public-contact-link : génération du lien signé (rôle
 *   manager), expiration bornée.
 */
class TravelPublicContactTest extends TestCase
{
    use RefreshTenantDatabase;

    private function actingEmployee(Company $company, string $role = 'manager', ?string $managerRole = 'principal'): Employee
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

    private function makeCompany(bool $withFeature = true): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        if ($withFeature) {
            $company->setFeature('travelagency', true);
            $company->save();
        }

        return $company;
    }

    /* ── POST /travel/public/contact (lien signé) ───────────────── */

    public function test_public_contact_rejects_unsigned_request(): void
    {
        $this->makeCompany();

        $this->postJson('/api/v1/travel/public/contact', [
            'email' => 'visiteur@example.com',
            'message' => 'Bonjour',
            'consent_email' => true,
        ])->assertStatus(403);
    }

    public function test_public_contact_accepts_signed_request(): void
    {
        $company = $this->makeCompany();

        $url = URL::signedRoute('travel.public.contact.store', ['company' => $company->id]);

        $this->postJson($url, [
            'first_name' => 'Visiteur',
            'last_name' => 'Public',
            'email' => 'visiteur@example.com',
            'phone' => '+237699999999',
            'message' => 'Demande d\'information depuis le formulaire public.',
            'consent_email' => true,
        ])->assertStatus(202)
            ->assertJson(['status' => 'received']);

        $event = TravelOutboxEvent::query()
            ->where('event_type', 'travel.contact.submitted.v1')
            ->firstOrFail();
        self::assertSame('visiteur@example.com', $event->payload_redacted['email']);

        $contact = TravelCustomerContact::query()->where('email', 'visiteur@example.com')->firstOrFail();
        self::assertSame($company->id, $contact->company_id, 'registre tenant-scoped');
        self::assertTrue($contact->email_consent_given);
    }

    public function test_public_contact_is_idempotent_per_email(): void
    {
        $company = $this->makeCompany();

        $url = URL::signedRoute('travel.public.contact.store', ['company' => $company->id]);

        $this->postJson($url, [
            'email' => 'deja@example.com',
            'message' => 'Première demande.',
            'consent_email' => true,
        ])->assertStatus(202);

        $this->postJson($url, [
            'email' => 'deja@example.com',
            'message' => 'Deuxième demande.',
            'consent_email' => true,
        ])->assertStatus(202);

        self::assertSame(
            1,
            TravelCustomerContact::query()->where('email', 'deja@example.com')->count(),
            'pas de doublon (contrainte unique company+email)',
        );
    }

    public function test_public_contact_rejects_missing_consent(): void
    {
        $company = $this->makeCompany();

        $url = URL::signedRoute('travel.public.contact.store', ['company' => $company->id]);

        $this->postJson($url, [
            'email' => 'sans-consent@example.com',
            'message' => 'Bonjour',
        ])->assertStatus(422);

        self::assertSame(0, TravelOutboxEvent::query()->count(), 'aucun événement sans consentement');
    }

    public function test_public_contact_rejects_tenant_without_feature(): void
    {
        $company = $this->makeCompany(false);

        $url = URL::signedRoute('travel.public.contact.store', ['company' => $company->id]);

        $this->postJson($url, [
            'email' => 'autre@example.com',
            'message' => 'Bonjour',
            'consent_email' => true,
        ])->assertStatus(404);
    }

    /* ── POST /travel/public-contact-link (génération du lien) ──── */

    public function test_link_generation_requires_manager_role(): void
    {
        $company = $this->makeCompany();
        $this->actingEmployee($company, 'employee', null);

        $this->postJson('/api/v1/travel/public-contact-link')->assertStatus(403);
    }

    public function test_link_generation_returns_signed_url(): void
    {
        $company = $this->makeCompany();
        $this->actingEmployee($company);

        $response = $this->postJson('/api/v1/travel/public-contact-link')
            ->assertStatus(201);

        $url = $response->json('data.contact_url');

        self::assertNotFalse(parse_url($url, PHP_URL_QUERY), 'URL signée attendue');
        self::assertStringContainsString('signature=', $url);
        self::assertStringContainsString('company='.$company->id, $url);

        // Le lien signé fonctionne réellement.
        $this->postJson($url, [
            'email' => 'via-lien@example.com',
            'message' => 'Demande via le lien public.',
            'consent_email' => true,
        ])->assertStatus(202);
    }

    public function test_link_generation_bounds_expiration(): void
    {
        $company = $this->makeCompany();
        $this->actingEmployee($company);

        $response = $this->postJson('/api/v1/travel/public-contact-link', [
            'expires_in_hours' => 999,
        ])->assertStatus(201);

        $expiresAt = $response->json('data.expires_at');
        self::assertNotNull($expiresAt);

        // Borne haute : 168 h (7 jours) maximum.
        $max = now()->addHours(168)->addMinute();
        self::assertLessThanOrEqual($max, new \DateTimeImmutable($expiresAt));
    }
}
