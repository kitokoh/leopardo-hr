<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-405..409 (#6057..#6061) — Paiements : initiate + callback signé
 * idempotent.
 *
 * Couvre l'initiation idempotente, la signature HMAC (rejet si invalide),
 * la vérification du montant, la résolution par RÉFÉRENCE (corrige le bug
 * historique gv-back qui cherchait par id) et le rejeu sans effet de bord.
 */
class TravelPaymentTest extends TestCase
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
     * @return array{booking: TravelBooking, reference: string}
     */
    private function pendingBooking(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 40]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 15000,
            ]);

            $booking = TravelBooking::factory()->create([
                'trip_id' => $trip->id,
                'status' => BookingStatus::PENDING,
                'passenger_count' => 1,
                'total_amount_minor' => 15000,
                'currency' => 'XAF',
            ]);

            $passenger = $booking->passengers()->create([
                'full_name' => 'Jean Dupont',
                'age_category' => 'adult',
                'class_id' => $class->id,
                'seat_number' => 1,
                'unit_price_minor' => 15000,
            ]);

            TravelTripSeat::query()
                ->where('trip_id', $trip->id)
                ->where('status', 'free')
                ->first()
                ?->forceFill([
                    'status' => 'reserved',
                    'booking_id' => $booking->id,
                    'passenger_id' => $passenger->id,
                ])->save();

            return ['booking' => $booking, 'reference' => $booking->reference];
        });
    }

    private function sign(array $payload): string
    {
        $secret = (string) config('travel.payments.callback_secret');
        $canonical = implode('|', [
            $payload['reference'],
            $payload['provider_reference'],
            (string) $payload['amount_minor'],
            $payload['currency'],
            $payload['status'],
        ]);

        return hash_hmac('sha256', $canonical, $secret);
    }

    public function test_initiate_creates_pending_payment(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['reference' => $reference] = $this->pendingBooking($company);

        $this->postJson('/api/v1/travel/payments/initiate', [
            'booking_reference' => $reference,
            'provider_code' => 'pvit',
            'idempotency_key' => 'pay-001',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', PaymentStatus::PENDING->value);
    }

    public function test_initiate_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['reference' => $reference] = $this->pendingBooking($company);

        $payload = [
            'booking_reference' => $reference,
            'provider_code' => 'cash',
            'idempotency_key' => 'pay-dup',
        ];

        $this->postJson('/api/v1/travel/payments/initiate', $payload)->assertStatus(201);
        $this->postJson('/api/v1/travel/payments/initiate', $payload)->assertStatus(200);
    }

    public function test_callback_resolves_by_reference_and_confirms(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['booking' => $booking, 'reference' => $reference] = $this->pendingBooking($company);

        $initiate = $this->postJson('/api/v1/travel/payments/initiate', [
            'booking_reference' => $reference,
            'provider_code' => 'pvit',
            'idempotency_key' => 'pay-cb',
        ])->assertStatus(201)
            ->json('data');

        // Le provider renvoie la référence d'initiation dans son callback.
        $providerReference = $initiate['provider_reference'];

        $payload = [
            'reference' => $reference,
            'provider_reference' => $providerReference,
            'amount_minor' => 15000,
            'currency' => 'XAF',
            'status' => 'confirmed',
        ];
        $payload['signature'] = $this->sign($payload);

        // Le callback est PUBLIC (pas d'auth utilisateur) — Sanctum::actingAs
        // reste actif mais la route n'exige pas d'auth.
        $this->postJson('/api/v1/travel/payments/callback', $payload)
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::CONFIRMED->value);

        $this->assertSame(BookingStatus::PENDING->value, $booking->refresh()->status->value);
        $this->assertSame(PaymentStatus::CONFIRMED->value, $booking->refresh()->payment_status->value);
    }

    public function test_callback_with_invalid_signature_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['reference' => $reference] = $this->pendingBooking($company);

        $this->postJson('/api/v1/travel/payments/callback', [
            'reference' => $reference,
            'provider_reference' => 'PVIT-X',
            'amount_minor' => 15000,
            'currency' => 'XAF',
            'status' => 'confirmed',
            'signature' => 'invalide',
        ])->assertStatus(403);
    }

    public function test_callback_with_wrong_amount_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['reference' => $reference] = $this->pendingBooking($company);

        $payload = [
            'reference' => $reference,
            'provider_reference' => 'PVIT-Y',
            'amount_minor' => 9999,
            'currency' => 'XAF',
            'status' => 'confirmed',
        ];
        $payload['signature'] = $this->sign($payload);

        $this->postJson('/api/v1/travel/payments/callback', $payload)->assertStatus(422);
    }

    public function test_callback_replay_returns_existing_result(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        ['reference' => $reference] = $this->pendingBooking($company);

        $initiate = $this->postJson('/api/v1/travel/payments/initiate', [
            'booking_reference' => $reference,
            'provider_code' => 'pvit',
            'idempotency_key' => 'pay-replay',
        ])->assertStatus(201)
            ->json('data');

        $payload = [
            'reference' => $reference,
            'provider_reference' => $initiate['provider_reference'],
            'amount_minor' => 15000,
            'currency' => 'XAF',
            'status' => 'confirmed',
        ];
        $payload['signature'] = $this->sign($payload);

        $this->postJson('/api/v1/travel/payments/callback', $payload)->assertOk();
        // Rejeu : même résultat, aucun effet de bord.
        $this->postJson('/api/v1/travel/payments/callback', $payload)
            ->assertOk()
            ->assertJsonPath('data.replayed', true);
    }

    public function test_callback_unknown_reference_returns_404(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/payments/callback', [
            'reference' => 'GV-INCONNU',
            'provider_reference' => 'PVIT-Z',
            'amount_minor' => 15000,
            'currency' => 'XAF',
            'status' => 'confirmed',
            'signature' => 'x',
        ])->assertStatus(404);
    }
}
