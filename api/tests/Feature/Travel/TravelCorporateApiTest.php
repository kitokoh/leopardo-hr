<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelCorporateAccount;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-803 (#6094) — Réservations de groupe / corporate.
 *
 * Couvre le critère d'acceptation : DEVIS respecté (même trajet, même
 * effectif, montant ≤ devis) et PLAFONDS corporate respectés (cumul des
 * réservations ouvertes ≤ crédit) ; taille minimale de groupe.
 */
class TravelCorporateApiTest extends TestCase
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

    /**
     * Trajet publié (tarif 10 000/adulte) + compte corporate plafonné.
     *
     * @return array{trip: int, class: int, account: int}
     */
    private function fixtures(Company $company, int $creditLimit = 100000): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $creditLimit): array {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 60]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 10000,
            ]);

            $account = TravelCorporateAccount::factory()->create([
                'company_id' => $company->id,
                'credit_limit_minor' => $creditLimit,
            ]);

            return ['trip' => $trip->id, 'class' => $class->id, 'account' => $account->id];
        });
    }

    private function passengerPayload(int $classId): array
    {
        return ['full_name' => 'Passager corporate', 'age_category' => 'adult', 'class_id' => $classId];
    }

    public function test_quote_flow_and_group_booking_respect_quote_and_credit_limit(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fx = $this->fixtures($company);

        // Devis 6 passagers × 10 000 = 60 000.
        $quote = $this->postJson('/api/v1/travel/corporate-quotes', [
            'corporate_account_id' => $fx['account'],
            'trip_id' => $fx['trip'],
            'class_id' => $fx['class'],
            'passengers_count' => 6,
        ])->assertStatus(201)->json('data');

        $this->assertSame(60000, $quote['total_amount_minor']);
        $this->assertSame('draft', $quote['status']);

        // Acceptation du devis.
        $this->postJson("/api/v1/travel/corporate-quotes/{$quote['id']}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        // Réservation groupée adossée au devis → 201, facturation différée.
        $passengers = array_fill(0, 6, $this->passengerPayload($fx['class']));
        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $fx['trip'],
            'booking_source' => 'office',
            'idempotency_key' => 'corp-1',
            'corporate_account_id' => $fx['account'],
            'quote_id' => $quote['id'],
            'passengers' => $passengers,
        ])->assertStatus(201)
            ->assertJsonPath('data.billing_deferred', true)
            ->assertJsonPath('data.corporate_account_id', $fx['account']);
    }

    public function test_credit_limit_is_enforced(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fx = $this->fixtures($company, creditLimit: 50000);

        $passengers = array_fill(0, 6, $this->passengerPayload($fx['class']));

        // 6 × 10 000 = 60 000 > 50 000 → plafond dépassé → 422.
        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $fx['trip'],
            'booking_source' => 'office',
            'idempotency_key' => 'corp-limit-1',
            'corporate_account_id' => $fx['account'],
            'passengers' => $passengers,
        ])->assertStatus(422);
    }

    public function test_min_group_size_is_enforced(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fx = $this->fixtures($company);

        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $fx['trip'],
            'booking_source' => 'office',
            'idempotency_key' => 'corp-small-1',
            'corporate_account_id' => $fx['account'],
            'passengers' => [$this->passengerPayload($fx['class']), $this->passengerPayload($fx['class'])],
        ])->assertStatus(422);
    }

    public function test_quote_mismatch_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fx = $this->fixtures($company);

        // Devis pour 6 passagers, accepté.
        $quote = $this->postJson('/api/v1/travel/corporate-quotes', [
            'corporate_account_id' => $fx['account'],
            'trip_id' => $fx['trip'],
            'class_id' => $fx['class'],
            'passengers_count' => 6,
        ])->assertStatus(201)->json('data');
        $this->postJson("/api/v1/travel/corporate-quotes/{$quote['id']}/accept")->assertOk();

        // Réservation avec SEULEMENT 4 passagers → devis non respecté → 422.
        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $fx['trip'],
            'booking_source' => 'office',
            'idempotency_key' => 'corp-mismatch-1',
            'corporate_account_id' => $fx['account'],
            'quote_id' => $quote['id'],
            'passengers' => array_fill(0, 4, $this->passengerPayload($fx['class'])),
        ])->assertStatus(422);
    }
}
