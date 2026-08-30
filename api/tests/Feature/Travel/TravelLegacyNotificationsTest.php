<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Mail\CommunicationMail;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-910 (#6113) — Notifications legacy gv-back → canaux plateforme.
 *
 * Aucune table « notifications » maison ; l'envoi manuel passe par les
 * canaux de la plateforme (email transactionnel / in-app BC-13) et
 * respecte le consentement (`travel_customer_contacts`).
 */
class TravelLegacyNotificationsTest extends TestCase
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

        Mail::fake();
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

    public function test_no_homegrown_notifications_table(): void
    {
        // La file maison gv-back n'est PAS reproduite : aucune table
        // travel_notifications / travel_manual_notifications.
        self::assertFalse(Schema::hasTable('travel_notifications'));
        self::assertFalse(Schema::hasTable('travel_manual_notifications'));
    }

    public function test_manual_notification_sent_when_channel_configured_with_consent(): void
    {
        $this->actingManager();
        $contact = $this->tenants->withinTenant($this->company, fn (): TravelCustomerContact => TravelCustomerContact::factory()->withEmailConsent()->create());

        $this->postJson('/api/v1/travel/contacts/'.$contact->id.'/notify', [
            'message' => 'Votre voyage est confirmé.',
        ])
            ->assertOk()
            ->assertJsonPath('data.channels', ['email']);

        Mail::assertSent(CommunicationMail::class, fn (CommunicationMail $mail): bool => $mail->hasTo($contact->email));
    }

    public function test_manual_notification_blocked_without_consent(): void
    {
        $this->actingManager();
        $contact = $this->tenants->withinTenant($this->company, fn (): TravelCustomerContact => TravelCustomerContact::factory()->create());

        $this->postJson('/api/v1/travel/contacts/'.$contact->id.'/notify', [
            'message' => 'Pas de consentement.',
        ])->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_manual_notification_requires_manage_role(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
        Sanctum::actingAs($employee);

        $contact = $this->tenants->withinTenant($this->company, fn (): TravelCustomerContact => TravelCustomerContact::factory()->withEmailConsent()->create());

        $this->postJson('/api/v1/travel/contacts/'.$contact->id.'/notify', [
            'message' => 'Rôle insuffisant.',
        ])->assertStatus(403);
    }
}
