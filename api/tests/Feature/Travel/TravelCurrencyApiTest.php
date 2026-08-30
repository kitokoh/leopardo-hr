<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-805 (#6096) — Multi-devise.
 *
 * Couvre le critère d'acceptation : AUCUNE perte d'arrondi (montants
 * canoniques conservés, conversion pure à l'affichage) et taux VALIDÉS
 * PAR PÉRIODE (hors fenêtre → 422) ; paire inverse supportée.
 */
class TravelCurrencyApiTest extends TestCase
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

    private function storeRate(Company $company, string $base, string $quote, string $rate, string $from, ?string $until = null): void
    {
        $this->postJson('/api/v1/travel/currency-rates', [
            'base_currency' => $base,
            'quote_currency' => $quote,
            'rate' => $rate,
            'valid_from' => $from,
            'valid_until' => $until,
        ])->assertStatus(201);
    }

    public function test_conversion_rounds_without_losing_canonical_amount(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        // 1 EUR = 600 XAF → 60 000 XAF = 100 EUR.
        $this->storeRate($company, 'EUR', 'XAF', '600', now()->toDateString());

        $this->getJson('/api/v1/travel/currency-rates/convert?amount_minor=60000&from=XAF&to=EUR')
            ->assertOk()
            ->assertJsonPath('data.converted_amount_minor', 100);

        // Aller-retour : aucun arrondi perdu sur le montant canonique.
        $this->getJson('/api/v1/travel/currency-rates/convert?amount_minor=100&from=EUR&to=XAF')
            ->assertOk()
            ->assertJsonPath('data.converted_amount_minor', 60000);
    }

    public function test_rate_is_only_valid_within_its_period(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->storeRate($company, 'EUR', 'XAF', '600', now()->subDays(30)->toDateString(), now()->subDays(10)->toDateString());

        // Hors fenêtre → 422 (taux non validé pour la période).
        $this->getJson('/api/v1/travel/currency-rates/convert?amount_minor=60000&from=XAF&to=EUR')
            ->assertStatus(422);
    }

    public function test_reverse_pair_is_supported(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        // Seule la paire EUR→XAF est saisie ; XAF→EUR utilise l'inverse.
        $this->storeRate($company, 'EUR', 'XAF', '600', now()->toDateString());

        $this->getJson('/api/v1/travel/currency-rates/convert?amount_minor=120000&from=XAF&to=EUR')
            ->assertOk()
            ->assertJsonPath('data.converted_amount_minor', 200);
    }

    public function test_shop_prices_are_converted_for_display(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->storeRate($company, 'EUR', 'XAF', '600', now()->toDateString());

        app(TenantManager::class)->withinTenant($company, function (): void {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 20]);
            app(GenerateTripSeatsAction::class)->execute($trip);
            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 60000,
            ]);
        });

        // Affichage en EUR : 60 000 XAF → 100 EUR.
        $this->getJson('/api/v1/travel/shop/trips?currency=EUR')
            ->assertOk()
            ->assertJsonPath('data.0.prices.0.adult_price_minor', 100)
            ->assertJsonPath('data.0.prices.0.currency', 'EUR');

        // Le montant canonique stocké est inchangé.
        $this->assertSame(60000, TravelTripPrice::query()->value('adult_price_minor'));
    }
}
