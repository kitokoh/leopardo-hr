<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-416 (#6068) — Formulaire de contact → événement outbox (lead CRM).
 *
 * Aucune écriture directe dans les tables CRM (garde d'isolation #5584) :
 * le contact est publié via `travel.contact.submitted.v1` et consommé par
 * le BC CRM.
 */
class TravelContactTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company): Employee
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

    public function test_contact_publishes_outbox_event(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/contact', [
            'name' => 'Jean Dupont',
            'email' => 'jean@example.com',
            'phone' => '+237600000000',
            'message' => 'Je souhaite un devis pour un voyage Douala→Yaoundé.',
            'consent' => true,
        ])->assertStatus(202)
            ->assertJsonPath('data.accepted', true);

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelOutboxEvent::query()
                ->where('event_type', 'travel.contact.submitted.v1')
                ->count();
        }));
    }

    public function test_contact_requires_consent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/contact', [
            'name' => 'Jean Dupont',
            'email' => 'jean@example.com',
            'message' => 'Sans consentement.',
            'consent' => false,
        ])->assertStatus(422);
    }

    public function test_contact_message_is_bounded(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/contact', [
            'name' => 'Jean Dupont',
            'email' => 'jean@example.com',
            'message' => str_repeat('a', 2001),
            'consent' => true,
        ])->assertStatus(422);
    }
}
