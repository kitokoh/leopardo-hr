<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
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

    /**
     * #7396 — la vente comptant au guichet (`POST /travel/bookings/{id}/confirm`)
     * doit créer la ligne `travel_payments` cash confirmée.
     *
     * Avant correctif : le chemin comptant ne basculait que
     * `booking.payment_status` → `cashPaidSince()` sommait 0 ligne → la caisse
     * attendue restait au fond de caisse et la vente apparaissait en écart.
     */
    private function pendingCashBooking(Company $company, int $amountMinor): TravelBooking
    {
        return app(TenantManager::class)->withinTenant(
            $company,
            fn (): TravelBooking => TravelBooking::factory()->create([
                'status' => BookingStatus::PENDING,
                'payment_status' => PaymentStatus::PENDING,
                'total_amount_minor' => $amountMinor,
                'currency' => 'XAF',
            ]),
        );
    }

    private function confirmCashBooking(TravelBooking $booking): void
    {
        $this->postJson("/api/v1/travel/bookings/{$booking->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CONFIRMED->value);
    }

    public function test_cash_confirmation_records_a_confirmed_cash_payment(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $booking = $this->pendingCashBooking($company, 600000);
        $this->confirmCashBooking($booking);

        $payments = app(TenantManager::class)->withinTenant(
            $company,
            fn () => TravelPayment::query()->where('booking_id', $booking->id)->get(),
        );

        self::assertCount(1, $payments, 'une vente comptant = exactement une ligne travel_payments');
        self::assertSame('cash', $payments->first()->provider_code->value);
        self::assertSame(PaymentStatus::CONFIRMED, $payments->first()->status);
        self::assertSame(600000, $payments->first()->amount_minor);
        self::assertSame($company->id, $payments->first()->company_id);
    }

    public function test_cash_confirmation_feeds_the_cash_session_expected_balance(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/pdv/session/open', ['opening_balance_minor' => 5000])
            ->assertStatus(201);

        // Deux ventes comptant au guichet : 600 000 + 40 000.
        $this->confirmCashBooking($this->pendingCashBooking($company, 600000));
        $this->confirmCashBooking($this->pendingCashBooking($company, 40000));

        // Attendu = fond de caisse (5 000) + ventes comptant (640 000).
        $this->postJson('/api/v1/travel/pdv/session/close', ['actual_balance_minor' => 645000])
            ->assertOk()
            ->assertJsonPath('data.expected_balance_minor', 645000)
            ->assertJsonPath('data.difference_minor', 0);
    }

    public function test_cash_confirmation_replay_does_not_double_the_payment(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $booking = $this->pendingCashBooking($company, 250000);
        $this->confirmCashBooking($booking);
        $this->confirmCashBooking($booking); // rejeu

        $count = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => TravelPayment::query()->where('booking_id', $booking->id)->count(),
        );

        self::assertSame(1, $count, 'le rejeu de la confirmation ne crée pas de double paiement');
    }
}
