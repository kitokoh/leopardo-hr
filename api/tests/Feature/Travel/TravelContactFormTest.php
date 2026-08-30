<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Interfaces\Api\V1\Controllers\TravelContactController;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-416 (#6068) — formulaire de contact → lead CRM.
 *
 * Verrouille : consentement obligatoire (422), bornes du message,
 * publication de l'événement `travel.contact.submitted.v1` SANS aucune
 * écriture directe dans les tables CRM, isolation par tenant, auth 401.
 */
class TravelContactFormTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'CM',
            'currency' => 'XAF',
            'features' => ['travelagency' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'SN',
            'currency' => 'XOF',
            'features' => ['travelagency' => true],
        ]);
        $this->companyB = $companyB;

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;
    }

    private function baseUrl(): string
    {
        return '/api/v1/travel';
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->postJson($this->baseUrl().'/contact', [])->assertStatus(401);
    }

    public function test_contact_requires_consent_and_bounds(): void
    {
        Sanctum::actingAs($this->principalA);
        $url = $this->baseUrl().'/contact';

        // Consentement manquant → 422.
        $this->postJson($url, [
            'name' => 'Awa Diallo',
            'email' => 'awa@example.com',
            'message' => 'Je cherche un billet Douala → Yaoundé.',
        ])->assertStatus(422);

        // Message trop long → 422.
        $this->postJson($url, [
            'name' => 'Awa Diallo',
            'email' => 'awa@example.com',
            'message' => str_repeat('a', 2001),
            'consent' => true,
        ])->assertStatus(422);

        // Aucun événement publié sur les échecs.
        $this->assertSame(0, TravelOutboxEvent::query()->count());
    }

    public function test_contact_publishes_event_without_crm_write(): void
    {
        Sanctum::actingAs($this->principalA);

        $this->postJson($this->baseUrl().'/contact', [
            'name' => 'Awa Diallo',
            'email' => 'awa@example.com',
            'phone' => '+237690000000',
            'message' => 'Je cherche un billet Douala → Yaoundé pour le 15/09.',
            'consent' => true,
        ])->assertStatus(202)->assertJsonPath('data.status', 'received');

        $event = TravelOutboxEvent::query()
            ->where('company_id', $this->companyA->id)
            ->where('event_type', TravelContactController::EVENT_CONTACT_SUBMITTED)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('awa@example.com', $event->payload_redacted['email'] ?? null);
        $this->assertTrue(($event->payload_redacted['consent'] ?? false) === true);

        // Aucune écriture directe dans les tables CRM.
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('crm_leads')->count());
    }

    public function test_contact_is_tenant_isolated(): void
    {
        Sanctum::actingAs($this->principalA);

        $this->postJson($this->baseUrl().'/contact', [
            'name' => 'Awa Diallo',
            'email' => 'awa@example.com',
            'message' => 'Demande A',
            'consent' => true,
        ])->assertStatus(202);

        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($principalB);

        $this->postJson($this->baseUrl().'/contact', [
            'name' => 'Fatou Ndiaye',
            'email' => 'fatou@example.com',
            'message' => 'Demande B',
            'consent' => true,
        ])->assertStatus(202);

        // Chaque tenant ne voit que SES événements.
        $this->assertSame(1, TravelOutboxEvent::query()->where('company_id', $this->companyA->id)->count());
        $this->assertSame(1, TravelOutboxEvent::query()->where('company_id', $this->companyB->id)->count());
    }
}
