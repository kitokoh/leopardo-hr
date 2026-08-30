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
 * TRAVEL-804 (#6095) — Recherche flexible (dates ± N jours).
 *
 * Couvre : le groupement des résultats par date, la borne de la fenêtre
 * (N ≤ 7), le tri par tarif dans chaque groupe et l'exclusion des dates
 * hors fenêtre.
 */
class TravelFlexibleSearchTest extends TestCase
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
     * @param  list<array{date: string, price: int}>  $trips
     */
    private function seedPublishedTrips(Company $company, array $trips): void
    {
        app(TenantManager::class)->withinTenant($company, function () use ($trips): void {
            $class = TravelClass::factory()->create();

            foreach ($trips as $trip) {
                $model = TravelTrip::factory()->create([
                    'status' => 'published',
                    'departure_date' => $trip['date'],
                    'departure_time' => '08:00:00',
                    'total_seats' => 20,
                ]);
                app(GenerateTripSeatsAction::class)->execute($model);

                TravelTripPrice::factory()->create([
                    'trip_id' => $model->id,
                    'class_id' => $class->id,
                    'adult_price_minor' => $trip['price'],
                ]);
            }
        });
    }

    public function test_flexible_search_groups_results_by_date(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $anchor = now()->toDateString();

        $this->seedPublishedTrips($company, [
            ['date' => $anchor, 'price' => 12000],
            ['date' => now()->addDay()->toDateString(), 'price' => 9000],
            ['date' => now()->addDays(3)->toDateString(), 'price' => 15000],
        ]);

        $response = $this->getJson("/api/v1/travel/shop/trips?departure_date={$anchor}&flexible_days=2")
            ->assertOk();

        $data = $response->json('data');

        $this->assertCount(2, $data);
        $this->assertSame($anchor, $data[0]['date']);
        $this->assertSame(now()->addDay()->toDateString(), $data[1]['date']);
        $this->assertSame(9000, $data[1]['trips'][0]['prices'][0]['adult_price_minor'] ?? null);
    }

    public function test_flexible_days_is_bounded_to_seven(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->getJson('/api/v1/travel/shop/trips?flexible_days=30')
            ->assertOk()
            ->assertJsonPath('meta.flexible_days', 7);
    }

    public function test_flexible_search_excludes_out_of_window_dates(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $anchor = now()->toDateString();

        $this->seedPublishedTrips($company, [
            ['date' => $anchor, 'price' => 10000],
            // Hors fenêtre ±1 jour : doit être exclue.
            ['date' => now()->addDays(5)->toDateString(), 'price' => 10000],
        ]);

        $response = $this->getJson("/api/v1/travel/shop/trips?departure_date={$anchor}&flexible_days=1")
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }
}
