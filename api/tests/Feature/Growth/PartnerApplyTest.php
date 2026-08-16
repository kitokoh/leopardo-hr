<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Billing\Domain\Models\Partner;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #4186 : la candidature partenaire perdait silencieusement les
 * coordonnées (name/email/phone/website/commission_rate ni colonnes ni
 * fillable) — ligne partners vide. Le chemin live est
 * PartnerDashboardController::apply → PartnerService::apply.
 */
class PartnerApplyTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_apply_persists_contact_fields(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/growth/partner/apply', [
            'type'            => 'individual',
            'name'            => 'Kabyle Consulting',
            'email'           => 'contact@kabyle.dz',
            'phone'           => '+213550000000',
            'website'         => 'https://kabyle.dz',
            'commission_rate' => 0.15,
        ]);

        $response->assertStatus(201);

        // resolveGlobalUser() crée/retrouve un User global lié à l'email employé.
        $userId = \App\Core\Auth\Domain\Models\User::where('email', $manager->email)->value('id');
        $this->assertNotNull($userId);

        $partner = Partner::where('user_id', $userId)->first();

        $this->assertNotNull($partner);
        $this->assertSame('Kabyle Consulting', $partner->name);
        $this->assertSame('contact@kabyle.dz', $partner->email);
        $this->assertSame('+213550000000', $partner->phone);
        $this->assertSame('https://kabyle.dz', $partner->website);
        $this->assertSame('0.15', (string) $partner->commission_rate);
        $this->assertSame('pending', $partner->application_status);
        $this->assertSame('suspended', $partner->status);
    }

    public function test_apply_without_contacts_still_works(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/growth/partner/apply', [
            'type' => 'accountant',
        ])->assertStatus(201);

        $userId = \App\Core\Auth\Domain\Models\User::where('email', $manager->email)->value('id');
        $partner = Partner::where('user_id', $userId)->first();
        $this->assertNotNull($partner);
        $this->assertNull($partner->name);
        $this->assertNull($partner->email);
    }
}
