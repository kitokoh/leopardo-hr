<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\ConfirmBookingAction;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\PaymentProvider;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7396 — Une vente comptant au guichet doit laisser une trace de paiement.
 *
 * Constat de recette : `ConfirmBookingAction` basculait `booking.payment_status`
 * à `confirmed` sans jamais créer de ligne `travel_payments`. La clôture de
 * caisse (`TravelPdvService::close()`, attendu = fond + paiements cash
 * confirmés) ne voyait donc AUCUNE vente : l'attendu valait toujours le fond de
 * caisse, et **chaque encaissement comptant ressortait en « écart »**. Le
 * rapprochement comptable ne voyait pas non plus ces recettes.
 *
 * Pourquoi la suite existante ne l'a pas vu : `TravelPdvApiTest` teste bien
 * l'arithmétique de la caisse, mais son helper `seedCashPayment()` **fabrique
 * lui-même la ligne `travel_payments`** — le test fournit ce que le code de
 * production aurait dû produire. Le chaînon « vendre au comptant → trace de
 * paiement » n'était couvert par AUCUN test.
 *
 * Ce fichier part de l'autre bout : il passe par le chemin réel
 * (création de réservation puis confirmation comptant) et vérifie que le
 * paiement existe et que la caisse tombe juste.
 */
class TravelCashPaymentRecordingTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TenantManager $tenants;

    private Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->company = $company;
        $this->tenants = app(TenantManager::class);

        $company->setFeature('travelagency', true);
        $company->save();

        /** @var Employee $principal */
        $principal = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principal = $principal;

        Sanctum::actingAs($principal);
    }

    public function test_counter_confirmation_records_a_confirmed_cash_payment(): void
    {
        [$booking, $total] = $this->pendingBookingWithPrice(600000);

        // Chemin RÉEL : ce que fait le guichetier.
        $this->postJson("/api/v1/travel/bookings/{$booking->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $payment = $this->tenants->withinTenant(
            $this->company,
            fn (): ?TravelPayment => TravelPayment::query()
                ->where('booking_id', $booking->id)
                ->first()
        );

        self::assertInstanceOf(TravelPayment::class, $payment, 'Une vente comptant doit créer une ligne de paiement.');
        self::assertSame(PaymentProvider::CASH, $payment->provider_code);
        self::assertSame(PaymentStatus::CONFIRMED, $payment->status);
        self::assertSame($total, $payment->amount_minor, 'Le montant doit être celui de la réservation (calculé serveur).');
        self::assertSame($this->company->currency, $payment->currency);
        self::assertSame(
            ConfirmBookingAction::CASH_IDEMPOTENCY_PREFIX.$booking->reference,
            $payment->idempotency_key,
            'La clé d’idempotence doit être déterministe (contrainte unique en base).',
        );
    }

    public function test_repeated_confirmation_never_duplicates_the_payment(): void
    {
        [$booking] = $this->pendingBookingWithPrice(150000);

        $this->postJson("/api/v1/travel/bookings/{$booking->id}/confirm")->assertOk();
        $this->postJson("/api/v1/travel/bookings/{$booking->id}/confirm")->assertOk();
        $this->postJson("/api/v1/travel/bookings/{$booking->id}/confirm")->assertOk();

        $count = $this->tenants->withinTenant(
            $this->company,
            fn (): int => TravelPayment::query()->where('booking_id', $booking->id)->count()
        );

        self::assertSame(1, $count, 'Un rejeu de confirmation ne doit jamais dupliquer le paiement.');
    }

    public function test_cash_session_closes_with_the_real_expected_balance(): void
    {
        $opening = 10000;
        $sale = 600000;

        $this->postJson('/api/v1/travel/pdv/session/open', ['opening_balance_minor' => $opening])
            ->assertStatus(201);

        [$booking] = $this->pendingBookingWithPrice($sale);

        // Vente comptant APRÈS l'ouverture de la caisse.
        $this->postJson("/api/v1/travel/bookings/{$booking->id}/confirm")->assertOk();

        // Le caissier compte 610 000 : fond + vente. Avant #7396 l'attendu
        // valait 10 000 et l'écart 600 000 — un caissier honnête passait pour
        // fautif à chaque clôture.
        $this->postJson('/api/v1/travel/pdv/session/close', ['actual_balance_minor' => $opening + $sale])
            ->assertOk()
            ->assertJsonPath('data.expected_balance_minor', $opening + $sale)
            ->assertJsonPath('data.actual_balance_minor', $opening + $sale)
            ->assertJsonPath('data.difference_minor', 0);
    }

    public function test_a_real_cash_difference_is_still_detected(): void
    {
        $opening = 5000;
        $sale = 200000;

        $this->postJson('/api/v1/travel/pdv/session/open', ['opening_balance_minor' => $opening])
            ->assertStatus(201);

        [$booking] = $this->pendingBookingWithPrice($sale);
        $this->postJson("/api/v1/travel/bookings/{$booking->id}/confirm")->assertOk();

        // Le caissier manque 20 000 : l'écart doit ressortir NÉGATIF.
        $this->postJson('/api/v1/travel/pdv/session/close', ['actual_balance_minor' => $opening + $sale - 20000])
            ->assertOk()
            ->assertJsonPath('data.expected_balance_minor', $opening + $sale)
            ->assertJsonPath('data.difference_minor', -20000);
    }

    /**
     * Crée un trajet publié tarifé puis une réservation EN ATTENTE au prix voulu.
     *
     * @return array{0: TravelBooking, 1: int}
     */
    private function pendingBookingWithPrice(int $totalMinor): array
    {
        return $this->tenants->withinTenant($this->company, function () use ($totalMinor): array {
            $trip = TravelTrip::factory()->create([
                'company_id' => $this->company->id,
                'status' => 'published',
                'total_seats' => 20,
            ]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create(['company_id' => $this->company->id]);
            TravelTripPrice::factory()->create([
                'company_id' => $this->company->id,
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => $totalMinor,
            ]);

            // Réservation via l'API pour rester sur le chemin réel (calcul du
            // montant côté serveur, sièges réservés, statut pending).
            $response = $this->postJson('/api/v1/travel/bookings', [
                'trip_id' => $trip->id,
                'booking_source' => 'office',
                'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
                'notify_consent' => false,
                'passengers' => [[
                    'full_name' => 'Client Guichet',
                    'age_category' => 'adult',
                    'class_id' => $class->id,
                ]],
            ])->assertStatus(201);

            $booking = TravelBooking::query()->findOrFail($response->json('data.id'));

            return [$booking, (int) $booking->total_amount_minor];
        });
    }
}
