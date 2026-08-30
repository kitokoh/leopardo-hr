<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCurrencyRate;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-805 (#6096) — Multi-devise.
 *
 * Taux par tenant avec périodes de validité ; conversion en math entière
 * (aucune perte d'arrondi) ; garde anti-chevauchement de périodes.
 */
class TravelCurrencyRateTest extends TestCase
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

    public function test_principal_creates_rate_and_converts_without_rounding_loss(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        // 1 EUR = 655,9570 XOF → rate_minor = 6559570.
        $this->postJson('/api/v1/travel/currency-rates', [
            'from_currency' => 'EUR',
            'to_currency' => 'XOF',
            'rate_minor' => 6559570,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
        ])->assertStatus(201)
            ->assertJsonPath('data.from_currency', 'EUR')
            ->assertJsonPath('data.rate_minor', 6559570);

        // 1,00 EUR = 100 minor → 65 595,70 XOF minor → arrondi 65 596.
        $this->getJson('/api/v1/travel/currency-rates/convert?amount=100&from=EUR&to=XOF&date=2026-06-01')
            ->assertOk()
            ->assertJsonPath('data.amount_minor', 65596)
            ->assertJsonPath('data.currency', 'XOF')
            ->assertJsonPath('data.rate_minor', 6559570);
    }

    public function test_convert_returns_identity_for_same_currency(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->getJson('/api/v1/travel/currency-rates/convert?amount=12345&from=XOF&to=XOF')
            ->assertOk()
            ->assertJsonPath('data.amount_minor', 12345)
            ->assertJsonPath('data.rate_minor', TravelCurrencyRate::RATE_SCALE);
    }

    public function test_overlapping_periods_are_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/currency-rates', [
            'from_currency' => 'EUR',
            'to_currency' => 'XOF',
            'rate_minor' => 6559570,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-06-30',
        ])->assertStatus(201);

        // Chevauchement sur [2026-06-01, ...] → 422.
        $this->postJson('/api/v1/travel/currency-rates', [
            'from_currency' => 'EUR',
            'to_currency' => 'XOF',
            'rate_minor' => 6600000,
            'valid_from' => '2026-06-01',
        ])->assertStatus(422);

        // Période strictement postérieure → acceptée.
        $this->postJson('/api/v1/travel/currency-rates', [
            'from_currency' => 'EUR',
            'to_currency' => 'XOF',
            'rate_minor' => 6600000,
            'valid_from' => '2026-07-01',
        ])->assertStatus(201);
    }

    public function test_latest_period_wins_for_the_date(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/currency-rates', [
            'from_currency' => 'EUR',
            'to_currency' => 'XOF',
            'rate_minor' => 6500000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-03-31',
        ])->assertStatus(201);

        $this->postJson('/api/v1/travel/currency-rates', [
            'from_currency' => 'EUR',
            'to_currency' => 'XOF',
            'rate_minor' => 6600000,
            'valid_from' => '2026-04-01',
        ])->assertStatus(201);

        // Février → ancien taux ; avril → nouveau taux.
        $this->getJson('/api/v1/travel/currency-rates/convert?amount=100&from=EUR&to=XOF&date=2026-02-15')
            ->assertOk()
            ->assertJsonPath('data.rate_minor', 6500000);

        $this->getJson('/api/v1/travel/currency-rates/convert?amount=100&from=EUR&to=XOF&date=2026-05-15')
            ->assertOk()
            ->assertJsonPath('data.rate_minor', 6600000);
    }

    public function test_convert_fails_when_no_rate_covers_the_date(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/currency-rates', [
            'from_currency' => 'EUR',
            'to_currency' => 'XOF',
            'rate_minor' => 6559570,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-06-30',
        ])->assertStatus(201);

        $this->getJson('/api/v1/travel/currency-rates/convert?amount=100&from=EUR&to=XOF&date=2026-12-01')
            ->assertStatus(422);
    }

    public function test_rates_are_isolated_per_tenant(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CI', 'currency' => 'XOF']);
        app(TenantManager::class)->withinTenant($other, function (): void {
            TravelCurrencyRate::factory()->create([
                'from_currency' => 'USD',
                'to_currency' => 'XOF',
                'rate_minor' => 6000000,
                'valid_from' => '2026-01-01',
            ]);
        });

        // Le taux du tenant voisin n'est pas visible.
        $this->getJson('/api/v1/travel/currency-rates/convert?amount=100&from=USD&to=XOF&date=2026-06-01')
            ->assertStatus(422);
    }
}
