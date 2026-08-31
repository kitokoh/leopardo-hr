<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelRoundTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-802 (#6093) — Billets aller-retour (round-trip).
 *
 * Couvre : la création des deux jambes liées (même groupe, `return_booking_id`),
 * le tarif combiné optionnel (remise serveur sur la jambe retour), et
 * l'annulation PAR SENS (l'aller peut être annulé sans toucher au retour).
 * TRAVEL-802 (#6093) — Aller-retour combiné.
 *
 * POST /travel/round-trips crée deux réservations liées (aller + retour),
 * idempotent par clé ; chaque sens reste annulable indépendamment et le
 * statut du combo est dérivé.
 */
class TravelRoundTripTest extends TestCase
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
     * @return array{trip: int, class: int}
     */
    private function publishedTrip(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 30]);
     * @return array{trip: TravelTrip, class: TravelClass}
     */
    private function publishedTripWithPrice(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 40]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 10000,
            ]);

            return ['trip' => $trip->id, 'class' => $class->id];
        });
    }

    public function test_round_trip_creates_two_linked_bookings(): void
                'adult_price_minor' => 15000,
                'child_price_minor' => 7500,
            ]);

            return ['trip' => $trip->refresh(), 'class' => $class];
        });
    }

    /**
     * @return array{outbound: TravelTrip, return: TravelTrip, class: TravelClass}
     */
    private function roundTripSetup(Company $company): array
    {
        ['trip' => $outbound, 'class' => $class] = $this->publishedTripWithPrice($company);
        ['trip' => $return] = $this->publishedTripWithPrice($company);

        return ['outbound' => $outbound, 'return' => $return, 'class' => $class];
    }

    public function test_principal_creates_round_trip_with_two_linked_bookings(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $outbound = $this->publishedTrip($company);
        $return = $this->publishedTrip($company);

        $response = $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $outbound['trip'],
            'booking_source' => 'office',
            'idempotency_key' => 'round-trip-1',
            'passengers' => [['full_name' => 'Jean Dupont', 'age_category' => 'adult', 'class_id' => $outbound['class']]],
            'return_trip_id' => $return['trip'],
            'return_passengers' => [['full_name' => 'Jean Dupont', 'age_category' => 'adult', 'class_id' => $return['class']]],
        ]);
        if ($response->status() !== 201) {
            fwrite(STDERR, json_encode($response->json()).PHP_EOL);
        }
        $response->assertStatus(201);

        $outbound = $response->json('data');
        $this->assertNotNull($outbound['round_trip_group_id']);
        $this->assertSame('outbound', $outbound['leg']);
        $this->assertNotNull($outbound['return_booking_id']);

        $returnBooking = TravelBooking::query()->findOrFail($outbound['return_booking_id']);
        $this->assertSame($outbound['round_trip_group_id'], $returnBooking->round_trip_group_id);
        $this->assertSame('return', $returnBooking->leg);

        // 2 réservations au total (aller + retour), pas de doublon.
        $this->assertSame(2, TravelBooking::query()->count());
    }

    public function test_round_trip_applies_combined_tariff_discount_on_return_leg(): void
    {
        config()->set('travel.pricing.round_trip_discount_percent', 10);

        $setup = $this->roundTripSetup($company);

        $response = $this->postJson('/api/v1/travel/round-trips', [
            'outbound_trip_id' => $setup['outbound']->id,
            'return_trip_id' => $setup['return']->id,
            'booking_source' => 'office',
            'idempotency_key' => 'rt-001',
            'passengers' => [
                ['full_name' => 'Jean Dupont', 'age_category' => 'adult', 'class_id' => $setup['class']->id],
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.outbound.status', BookingStatus::PENDING->value)
            ->assertJsonPath('data.return.status', BookingStatus::PENDING->value)
            ->assertJsonPath('data.outbound.passenger_count', 1)
            ->assertJsonPath('data.return.passenger_count', 1);

        $reference = $response->json('data.reference');
        $this->assertStringStartsWith('RT-', $reference);

        // Deux réservations + un lien, aucune réservation orpheline.
        $this->assertSame(2, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelBooking::query()->count();
        }));
        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelRoundTrip::query()->count();
        }));
    }

    public function test_same_idempotency_key_returns_existing_round_trip(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $outbound = $this->publishedTrip($company);
        $return = $this->publishedTrip($company);

        $response = $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $outbound['trip'],
            'booking_source' => 'office',
            'idempotency_key' => 'round-trip-2',
            'passengers' => [['full_name' => 'A', 'age_category' => 'adult', 'class_id' => $outbound['class']]],
            'return_trip_id' => $return['trip'],
            'return_passengers' => [['full_name' => 'A', 'age_category' => 'adult', 'class_id' => $return['class']]],
        ])->assertStatus(201);

        $outbound = $response->json('data');
        $returnBooking = TravelBooking::query()->findOrFail($outbound['return_booking_id']);

        // Aller : 10 000 ; retour : 10 000 − 10 % = 9 000.
        $this->assertSame(10000, $outbound['total_amount_minor']);
        $this->assertSame(9000, $returnBooking->total_amount_minor);
    }

    public function test_round_trip_can_be_cancelled_per_leg(): void
        $setup = $this->roundTripSetup($company);

        $payload = [
            'outbound_trip_id' => $setup['outbound']->id,
            'return_trip_id' => $setup['return']->id,
            'booking_source' => 'office',
            'idempotency_key' => 'rt-dup',
            'passengers' => [
                ['full_name' => 'Jean Dupont', 'age_category' => 'adult', 'class_id' => $setup['class']->id],
            ],
        ];

        $this->postJson('/api/v1/travel/round-trips', $payload)->assertStatus(201);
        $this->postJson('/api/v1/travel/round-trips', $payload)->assertStatus(201);

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelRoundTrip::query()->count();
        }));
        $this->assertSame(2, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelBooking::query()->count();
        }));
    }

    public function test_cancelling_one_leg_marks_round_trip_partially_cancelled(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $outbound = $this->publishedTrip($company);
        $return = $this->publishedTrip($company);

        $response = $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $outbound['trip'],
            'booking_source' => 'office',
            'idempotency_key' => 'round-trip-3',
            'passengers' => [['full_name' => 'A', 'age_category' => 'adult', 'class_id' => $outbound['class']]],
            'return_trip_id' => $return['trip'],
            'return_passengers' => [['full_name' => 'A', 'age_category' => 'adult', 'class_id' => $return['class']]],
        ])->assertStatus(201);

        $outboundId = $response->json('data.id');
        $returnId = $response->json('data.return_booking_id');

        $this->postJson("/api/v1/travel/bookings/{$outboundId}/cancel", [
            'reason' => 'Changement de programme',
        ])->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);

        // La jambe retour reste intacte.
        $returnBooking = TravelBooking::query()->findOrFail($returnId);
        $this->assertSame(BookingStatus::PENDING, $returnBooking->status);
        $setup = $this->roundTripSetup($company);

        $response = $this->postJson('/api/v1/travel/round-trips', [
            'outbound_trip_id' => $setup['outbound']->id,
            'return_trip_id' => $setup['return']->id,
            'booking_source' => 'office',
            'idempotency_key' => 'rt-cancel-1',
            'passengers' => [
                ['full_name' => 'Jean Dupont', 'age_category' => 'adult', 'class_id' => $setup['class']->id],
            ],
        ])->assertStatus(201);

        $outboundBookingId = $response->json('data.outbound.id');

        // Annulation du sens aller (réservation standard, motif obligatoire).
        $this->postJson("/api/v1/travel/bookings/{$outboundBookingId}/cancel", [
            'reason' => 'Changement de programme',
        ])->assertStatus(200);

        $this->getJson('/api/v1/travel/round-trips/'.$response->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_cancelled')
            ->assertJsonPath('data.outbound.status', 'cancelled')
            ->assertJsonPath('data.return.status', 'pending');
    }

    public function test_round_trip_rejects_cross_tenant_return_trip(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $setup = $this->roundTripSetup($company);

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CI', 'currency' => 'XOF']);
        $otherTrip = app(TenantManager::class)->withinTenant($other, fn (): TravelTrip => TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 40]));

        $this->postJson('/api/v1/travel/round-trips', [
            'outbound_trip_id' => $setup['outbound']->id,
            'return_trip_id' => $otherTrip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'rt-cross-1',
            'passengers' => [
                ['full_name' => 'Jean Dupont', 'age_category' => 'adult', 'class_id' => $setup['class']->id],
            ],
        ])->assertStatus(404);
    }
}
