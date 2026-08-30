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
 * TRAVEL-416 (#6068) — Formulaire de contact → lead CRM.
 *
 * POST /api/v1/travel/contact : validation stricte (nom, email OU téléphone,
 * message borné, consentement RGPD), publication asynchrone de l'événement
 * `travel.contact.submitted.v1` dans l'outbox TravelAgency, isolation
 * cross-tenant et idempotence de rejeu.
 */
class TravelContactFormTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->company = $company;

        $company->setFeature('travelagency', true);
        $company->save();

        $this->tenants = app(TenantManager::class);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Awa Ngono',
            'email' => 'awa@example.com',
            'phone' => null,
            'message' => 'Bonjour, je souhaite des informations sur les trajets Douala–Yaoundé.',
            'consent' => true,
        ], $overrides);
    }

    public function test_valid_submission_publishes_contact_event(): void
    {
        $this->tenants->withinTenant($this->company, function (): void {
            $this->postJson('/api/v1/travel/contact', $this->payload())
                ->assertStatus(202)
                ->assertJson(['message' => 'Demande envoyée.']);

            $event = TravelOutboxEvent::query()
                ->where('event_type', 'travel.contact.submitted.v1')
                ->firstOrFail();

            $this->assertSame(TravelOutboxEvent::STATUS_PENDING, $event->status);
            $this->assertSame($this->company->id, $event->company_id);
            $this->assertSame('Awa Ngono', $event->payload_redacted['name']);
            $this->assertSame('awa@example.com', $event->payload_redacted['email']);
            $this->assertNull($event->payload_redacted['phone']);
            $this->assertTrue($event->payload_redacted['consent_given']);
            $this->assertSame('api.v1.contact', $event->payload_redacted['source']);
        });
    }

    public function test_consent_is_required(): void
    {
        $this->tenants->withinTenant($this->company, function (): void {
            $this->postJson('/api/v1/travel/contact', $this->payload(['consent' => false]))
                ->assertStatus(422);

            $this->postJson('/api/v1/travel/contact', $this->payload(['consent' => null]))
                ->assertStatus(422);
        });
    }

    public function test_message_is_bounded(): void
    {
        $this->tenants->withinTenant($this->company, function (): void {
            $this->postJson('/api/v1/travel/contact', $this->payload(['message' => 'trop court']))
                ->assertStatus(422);

            $this->postJson('/api/v1/travel/contact', $this->payload(['message' => str_repeat('a', 1001)]))
                ->assertStatus(422);
        });
    }

    public function test_email_or_phone_is_required(): void
    {
        $this->tenants->withinTenant($this->company, function (): void {
            $this->postJson('/api/v1/travel/contact', $this->payload(['email' => null, 'phone' => null]))
                ->assertStatus(422);
        });
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->tenants->withinTenant($this->company, function (): void {
            $this->postJson('/api/v1/travel/contact', $this->payload(['email' => 'pas-un-email']))
                ->assertStatus(422);
        });
    }

    public function test_phone_only_submission_is_accepted(): void
    {
        $this->tenants->withinTenant($this->company, function (): void {
            $this->postJson('/api/v1/travel/contact', $this->payload(['email' => null, 'phone' => '+237699000000']))
                ->assertStatus(202);

            $event = TravelOutboxEvent::query()
                ->where('event_type', 'travel.contact.submitted.v1')
                ->firstOrFail();

            $this->assertSame('+237699000000', $event->payload_redacted['phone']);
            $this->assertNull($event->payload_redacted['email']);
        });
    }

    public function test_replay_with_same_idempotency_key_creates_single_event(): void
    {
        $this->tenants->withinTenant($this->company, function (): void {
            $payload = $this->payload(['idempotency_key' => 'submission-uuid-001']);

            $this->postJson('/api/v1/travel/contact', $payload)->assertStatus(202);
            $this->postJson('/api/v1/travel/contact', $payload)->assertStatus(202);

            // La clé fournie est utilisée telle quelle : la contrainte unique
            // (company_id, idempotency_key) déduplique le rejeu → 1 seul événement.
            $this->assertSame(
                1,
                TravelOutboxEvent::query()
                    ->where('event_type', 'travel.contact.submitted.v1')
                    ->where('idempotency_key', 'submission-uuid-001')
                    ->count(),
            );
        });
    }

    public function test_event_is_isolated_to_the_submitting_tenant(): void
    {
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::factory()->create([
            'company_id' => $otherCompany->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($otherEmployee);

        $this->tenants->withinTenant($otherCompany, function () use ($otherCompany): void {
            $otherCompany->setFeature('travelagency', true);
            $otherCompany->save();

            $this->postJson('/api/v1/travel/contact', $this->payload())->assertStatus(202);
        });

        // L'événement appartient au tenant soumissionnaire, pas au tenant A.
        $this->tenants->withinTenant($this->company, function (): void {
            $this->assertSame(0, TravelOutboxEvent::query()->count());
        });

        $this->tenants->withinTenant($otherCompany, function (): void {
            $event = TravelOutboxEvent::query()
                ->where('event_type', 'travel.contact.submitted.v1')
                ->firstOrFail();
            $this->assertSame($otherCompany->id, $event->company_id);
        });
    }
}
