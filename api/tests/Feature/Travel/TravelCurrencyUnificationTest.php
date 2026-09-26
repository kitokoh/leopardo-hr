<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8168 — Unification des deux services de conversion de devises Travel.
 *
 * Avant ce correctif, la boutique (`TravelShopController`) lisait les
 * colonnes legacy `base_currency`/`quote_currency`/`rate` via
 * `TravelCurrencyService`, alors que l'API n'écrit que le contrat canonique
 * `from_currency`/`to_currency`/`rate_minor` : un taux configuré via
 * `POST /travel/currency-rates` était INVISIBLE pour l'affichage boutique
 * (critère TRAVEL-805 « conversion affichage » inapplicable).
 *
 * Couvre :
 * - un taux créé via l'API est utilisé par `GET /travel/shop/trips?currency=…`
 *   (paire directe ET paire inverse — critère « paire inverse supportée ») ;
 * - la paire inverse sur l'endpoint `currency-rates/convert`, en math
 *   entière (aucun flottant) ;
 * - le fail-closed 422 de la boutique quand aucun taux ne couvre la date ;
 * - l'invariance du montant canonique stocké (conversion à l'affichage).
 */
class TravelCurrencyUnificationTest extends TestCase
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
     * @return array{trip: TravelTrip, class: TravelClass}
     */
    private function publishedTrip(Company $company, int $adultPriceMinor, ?int $childPriceMinor = null, string $currency = 'XAF'): array
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($adultPriceMinor, $childPriceMinor, $currency): array {
            $origin = TravelCity::factory()->create(['name' => 'Douala']);
            $dest = TravelCity::factory()->create(['name' => 'Yaoundé']);
            $route = TravelRoute::factory()->create([
                'origin_city_id' => $origin->id,
                'destination_city_id' => $dest->id,
            ]);

            $trip = TravelTrip::factory()->create([
                'route_id' => $route->id,
                'status' => 'published',
                'total_seats' => 40,
            ]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => $adultPriceMinor,
                'child_price_minor' => $childPriceMinor,
                'currency' => $currency,
            ]);

            return ['trip' => $trip->refresh(), 'class' => $class];
        });
    }

    public function test_rate_created_via_api_is_used_by_shop_display_direct_pair(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        // Paire directe XAF→EUR : 1 XAF = 0,0020 EUR (rate_minor = 20).
        $this->postJson('/api/v1/travel/currency-rates', [
            'from_currency' => 'XAF',
            'to_currency' => 'EUR',
            'rate_minor' => 20,
            'valid_from' => now()->toDateString(),
        ])->assertStatus(201);

        $this->publishedTrip($company, adultPriceMinor: 60000, childPriceMinor: 30000);

        // 60 000 XAF × 20 / 10 000 = 120 EUR (minor) — AVANT le correctif,
        // ce taux configuré via l'API était invisible pour la boutique (422).
        $this->getJson('/api/v1/travel/shop/trips?currency=EUR')
            ->assertOk()
            ->assertJsonPath('data.0.prices.0.adult_price_minor', 120)
            ->assertJsonPath('data.0.prices.0.child_price_minor', 60)
            ->assertJsonPath('data.0.prices.0.currency', 'EUR');

        // Le montant canonique stocké n'est jamais modifié par l'affichage.
        $this->assertSame(60000, TravelTripPrice::query()->value('adult_price_minor'));
        $this->assertSame('XAF', TravelTripPrice::query()->value('currency'));
    }

    public function test_rate_created_via_api_is_used_by_shop_display_inverse_pair(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        // Seule la paire EUR→XAF est saisie (1 EUR = 600 XAF) ; l'affichage
        // en EUR de prix stockés en XAF utilise la paire INVERSE.
        $this->postJson('/api/v1/travel/currency-rates', [
            'from_currency' => 'EUR',
            'to_currency' => 'XAF',
            'rate_minor' => 6000000,
            'valid_from' => now()->toDateString(),
        ])->assertStatus(201);

        $this->publishedTrip($company, adultPriceMinor: 60000);

        // 60 000 XAF × 10 000 / 6 000 000 = 100 EUR (minor), math entière.
        $this->getJson('/api/v1/travel/shop/trips?currency=EUR')
            ->assertOk()
            ->assertJsonPath('data.0.prices.0.adult_price_minor', 100)
            ->assertJsonPath('data.0.prices.0.currency', 'EUR');

        $this->assertSame(60000, TravelTripPrice::query()->value('adult_price_minor'));
    }

    public function test_convert_endpoint_supports_inverse_pair_with_integer_math(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/currency-rates', [
            'from_currency' => 'EUR',
            'to_currency' => 'XAF',
            'rate_minor' => 6000000,
            'valid_from' => now()->toDateString(),
        ])->assertStatus(201);

        // Inverse : 120 000 XAF → 200 EUR ; rate_minor = taux STOCKÉ (sens
        // direct), `inverse` signale le sens appliqué.
        $this->getJson('/api/v1/travel/currency-rates/convert?amount=120000&from=XAF&to=EUR')
            ->assertOk()
            ->assertJsonPath('data.amount_minor', 200)
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.rate_minor', 6000000)
            ->assertJsonPath('data.inverse', true);

        // Aller-retour direct : 200 EUR → 120 000 XAF, aucune perte.
        $this->getJson('/api/v1/travel/currency-rates/convert?amount=200&from=EUR&to=XAF')
            ->assertOk()
            ->assertJsonPath('data.amount_minor', 120000)
            ->assertJsonPath('data.inverse', false);
    }

    public function test_direct_pair_wins_over_inverse_when_both_exist(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/currency-rates', [
            'from_currency' => 'EUR',
            'to_currency' => 'XAF',
            'rate_minor' => 6000000,
            'valid_from' => now()->toDateString(),
        ])->assertStatus(201);

        // Paire directe explicite différente de l'inverse (spread réel).
        $this->postJson('/api/v1/travel/currency-rates', [
            'from_currency' => 'XAF',
            'to_currency' => 'EUR',
            'rate_minor' => 15,
            'valid_from' => now()->toDateString(),
        ])->assertStatus(201);

        // 60 000 XAF × 15 / 10 000 = 90 EUR — la paire directe prime.
        $this->getJson('/api/v1/travel/currency-rates/convert?amount=60000&from=XAF&to=EUR')
            ->assertOk()
            ->assertJsonPath('data.amount_minor', 90)
            ->assertJsonPath('data.inverse', false);
    }

    public function test_shop_display_still_fails_closed_without_rate(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->publishedTrip($company, adultPriceMinor: 60000);

        // Aucun taux configuré → 422 explicite (jamais de conversion à 0),
        // comportement identique à l'ancien service.
        $this->getJson('/api/v1/travel/shop/trips?currency=EUR')
            ->assertStatus(422);
    }

    public function test_rates_remain_isolated_per_tenant_after_unification(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        $this->activateTravel($company);
        $this->activateTravel($other);
        $this->principal($company);

        // Le taux appartient à un AUTRE tenant : invisible pour la boutique
        // du tenant courant (le scope tenant du converter remplace le filtre
        // explicite de l'ancien service).
        app(TenantManager::class)->withinTenant($other, function (): void {
            \App\Modules\TravelAgency\Domain\Models\TravelCurrencyRate::factory()->create([
                'from_currency' => 'XAF',
                'to_currency' => 'EUR',
                'rate_minor' => 20,
                'valid_from' => now()->toDateString(),
            ]);
        });

        $this->publishedTrip($company, adultPriceMinor: 60000);

        $this->getJson('/api/v1/travel/shop/trips?currency=EUR')
            ->assertStatus(422);
    }
}
