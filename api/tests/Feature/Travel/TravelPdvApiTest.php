<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-810 (#6100) — Point de vente tablette.
 *
 * Couvre le critère d'acceptation : la clôture de caisse est COHÉRENTE
 * avec les paiements cash (attendu = solde initial + cash confirmés) ;
 * une seule session ouverte par tenant ; écart calculé serveur.
 */
class TravelPdvApiTest extends TestCase
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

    private function seedCashPayment(Company $company, int $amount): void
    {
        app(TenantManager::class)->withinTenant($company, function () use ($company, $amount): void {
            $booking = TravelBooking::factory()->create();

            TravelPayment::factory()->create([
                'company_id' => $company->id,
                'booking_id' => $booking->id,
                'provider_code' => 'cash',
                'status' => PaymentStatus::CONFIRMED,
                'amount_minor' => $amount,
                'currency' => 'XAF',
            ]);
        });
    }

    public function test_open_and_close_with_consistent_cash(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/pdv/session/open', ['opening_balance_minor' => 5000])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'open');

        // Encaissements cash confirmés depuis l'ouverture : 10 000 + 5 000.
        $this->seedCashPayment($company, 10000);
        $this->seedCashPayment($company, 5000);

        // Attendu = 5 000 + 15 000 = 20 000 ; réel = 20 000 → écart 0.
        $this->postJson('/api/v1/travel/pdv/session/close', ['actual_balance_minor' => 20000])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.expected_balance_minor', 20000)
            ->assertJsonPath('data.difference_minor', 0);
    }

    public function test_close_computes_difference_from_actual(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/pdv/session/open', ['opening_balance_minor' => 0])->assertStatus(201);
        $this->seedCashPayment($company, 8000);

        // Réel saisi 7 500 → écart −500 (déficit).
        $this->postJson('/api/v1/travel/pdv/session/close', ['actual_balance_minor' => 7500])
            ->assertOk()
            ->assertJsonPath('data.expected_balance_minor', 8000)
            ->assertJsonPath('data.difference_minor', -500);
    }

    public function test_only_one_open_session_per_tenant(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/pdv/session/open')->assertStatus(201);
        $this->postJson('/api/v1/travel/pdv/session/open')->assertStatus(422);
    }
}
