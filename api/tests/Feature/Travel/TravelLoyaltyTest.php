<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyAccount;
use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyEntry;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-811 (#6101) — Fidélité voyageur, contrat UNIFIÉ (#7445).
 *
 * Cette suite exerçait l'implémentation PERDANTE : clé de contact `contact_id`
 * (colonne inexistante), journal `travel_loyalty_transactions` (table qu'aucune
 * migration ne crée) et routes joker `int` `/loyalty/{contact}`. Elle ne
 * pouvait pas passer : `/loyalty/opt-in` répondait 500 (`NOT NULL
 * contact_identifier` puis `Undefined column contact_id`).
 *
 * Elle exerce désormais le contrat retenu (option B de l'issue) — la clé de
 * contact est `contact_identifier` (email/téléphone normalisé), le journal est
 * `travel_loyalty_entries`, et l'unique implémentation est
 * `TravelLoyaltyService` :
 *
 * - opt-in RGPD explicite requis, sinon aucun crédit ;
 * - points crédités UNE seule fois par billet (rejeu d'émission sans effet) ;
 * - opt-out : les crédits suivants sont gelés, le solde reste consultable ;
 * - échange de points → avoir (1 point = 10 unités mineures) ;
 * - `/loyalty/account`, `/loyalty/entries` et `/loyalty/redeem` ne sont pas
 *   capturés par le joker `/loyalty/{contact}` (régression #7445).
 */
class TravelLoyaltyTest extends TestCase
{
    use RefreshTenantDatabase;

    private const CONTACT = 'passager.fidele@example.dz';

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
     * Réservation confirmée pour un contact donné (email = clé de fidélité).
     *
     * @return array{booking: array<string, mixed>, passengerId: int}
     */
    private function confirmedBooking(Company $company, string $contactEmail): array
    {
        // La classe doit être celle du tarif créé pour CE trajet : un
        // `TravelClass::query()->firstOrFail()` renvoyait la classe du trajet
        // précédent lors d'un second appel dans le même test, et la création de
        // réservation échouait alors en 422 « Aucun tarif defini pour cette
        // classe sur ce trajet ».
        $classId = 0;

        $trip = app(TenantManager::class)->withinTenant($company, function () use (&$classId): TravelTrip {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 40]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            $classId = (int) $class->id;

            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 15000,
                'child_price_minor' => 7500,
            ]);

            return $trip->refresh();
        });

        $booking = $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'loyalty-bk-'.uniqid(),
            'contact_email' => $contactEmail,
            'passengers' => [
                ['full_name' => 'Passager Fidele', 'age_category' => 'adult', 'class_id' => $classId],
            ],
        ])->assertStatus(201);

        $bookingId = $booking->json('data.id');
        $this->postJson("/api/v1/travel/bookings/{$bookingId}/confirm")->assertOk();

        return [
            'booking' => $booking->json('data'),
            'passengerId' => (int) $booking->json('data.passengers.0.id'),
        ];
    }

    /** Points crédités par billet (barème de configuration, repli 10). */
    private function pointsPerTrip(): int
    {
        return (int) config('travel.loyalty.default_points_per_trip', 10);
    }

    private function balance(string $contact): int
    {
        return (int) $this->getJson('/api/v1/travel/loyalty/'.rawurlencode($contact))
            ->assertOk()
            ->json('data.points_balance');
    }

    public function test_points_are_credited_once_per_ticket_when_opted_in(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        // Opt-in explicite, clé de contact = contact_identifier.
        $this->postJson('/api/v1/travel/loyalty/opt-in', [
            'contact_identifier' => self::CONTACT,
        ])->assertOk()
            ->assertJsonPath('data.opted_in', true)
            ->assertJsonPath('data.points_balance', 0);

        $setup = $this->confirmedBooking($company, self::CONTACT);
        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/issue-ticket')->assertOk();

        $this->getJson('/api/v1/travel/loyalty/'.rawurlencode(self::CONTACT))
            ->assertOk()
            ->assertJsonPath('data.points_balance', $this->pointsPerTrip())
            ->assertJsonPath('data.opted_in', true);

        // Rejeu de l'émission : aucun crédit supplémentaire.
        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/issue-ticket')->assertOk();

        $this->assertSame($this->pointsPerTrip(), $this->balance(self::CONTACT));

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelLoyaltyEntry::query()->where('type', TravelLoyaltyEntry::TYPE_EARNED)->count();
        }));
    }

    public function test_no_points_without_opt_in(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        // Pas d'opt-in : l'émission ne crédite rien et ne crée aucun compte.
        $setup = $this->confirmedBooking($company, 'sans-optin@example.dz');
        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/issue-ticket')->assertOk();

        $this->getJson('/api/v1/travel/loyalty/sans-optin%40example.dz')
            ->assertOk()
            ->assertJsonPath('data.points_balance', 0)
            ->assertJsonPath('data.opted_in', false);

        $this->assertSame(0, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelLoyaltyAccount::query()->count();
        }), 'Aucun compte ne doit exister sans opt-in explicite (RGPD).');
    }

    public function test_opt_out_freezes_credits_but_keeps_the_balance(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/loyalty/opt-in', ['contact_identifier' => self::CONTACT])->assertOk();

        $setup = $this->confirmedBooking($company, self::CONTACT);
        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/issue-ticket')->assertOk();

        $this->postJson('/api/v1/travel/loyalty/opt-out', ['contact_identifier' => self::CONTACT])
            ->assertOk()
            ->assertJsonPath('data.opted_in', false);

        // Nouvelle réservation + émission : plus aucun crédit (opt-out).
        $setup2 = $this->confirmedBooking($company, self::CONTACT);
        $this->postJson('/api/v1/travel/bookings/'.$setup2['booking']['id'].'/issue-ticket')->assertOk();

        $this->getJson('/api/v1/travel/loyalty/'.rawurlencode(self::CONTACT))
            ->assertOk()
            ->assertJsonPath('data.points_balance', $this->pointsPerTrip())
            ->assertJsonPath('data.opted_in', false);
    }

    public function test_redeem_converts_points_to_a_credit_note(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/loyalty/opt-in', ['contact_identifier' => self::CONTACT])->assertOk();
        $setup = $this->confirmedBooking($company, self::CONTACT);
        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/issue-ticket')->assertOk();

        $points = $this->pointsPerTrip();
        $burn = max(1, intdiv($points, 2));

        // 1 point = 10 unités mineures d'avoir (TravelLoyaltyService::REDEEM_RATE).
        $this->postJson('/api/v1/travel/loyalty/'.rawurlencode(self::CONTACT).'/redeem', [
            'points' => $burn,
            'booking_id' => $setup['booking']['id'],
        ])->assertOk()
            ->assertJsonPath('data.discount_minor', $burn * 10)
            ->assertJsonPath('data.points_burned', $burn);

        $this->assertSame($points - $burn, $this->balance(self::CONTACT));

        // Le débit est journalisé dans le MÊME journal que les crédits (#7445).
        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelLoyaltyEntry::query()->where('type', TravelLoyaltyEntry::TYPE_REDEEMED)->count();
        }));
    }

    public function test_redeem_rejects_insufficient_balance(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/loyalty/opt-in', ['contact_identifier' => self::CONTACT])->assertOk();

        $this->postJson('/api/v1/travel/loyalty/'.rawurlencode(self::CONTACT).'/redeem', [
            'points' => $this->pointsPerTrip(),
        ])->assertStatus(422);
    }

    /**
     * `/loyalty/redeem` (catalogue de récompenses) était déclaré deux fois, la
     * dernière pointant vers une méthode exigeant un paramètre de route absent
     * → 500. Il répond désormais 200 et débite le journal.
     */
    public function test_redeem_reward_from_catalogue(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/loyalty/opt-in', ['contact_identifier' => self::CONTACT])->assertOk();
        $setup = $this->confirmedBooking($company, self::CONTACT);
        $this->postJson('/api/v1/travel/bookings/'.$setup['booking']['id'].'/issue-ticket')->assertOk();

        $cost = max(1, intdiv($this->pointsPerTrip(), 2));

        $reward = $this->postJson('/api/v1/travel/loyalty/rewards', [
            'name' => 'Surclassement',
            'points_cost' => $cost,
        ])->assertStatus(201);

        $this->postJson('/api/v1/travel/loyalty/redeem', [
            'contact_identifier' => self::CONTACT,
            'reward_id' => (int) $reward->json('data.id'),
            'booking_id' => (int) $setup['booking']['id'],
        ])->assertOk()
            ->assertJsonPath('data.points', -$cost);

        $this->assertSame($this->pointsPerTrip() - $cost, $this->balance(self::CONTACT));
    }

    /**
     * Régression #7445 : `/loyalty/account`, `/loyalty/entries` et
     * `/loyalty/rewards` ne doivent pas être capturés par le joker
     * `/loyalty/{contact}` (segment réservé).
     */
    public function test_named_routes_are_not_captured_by_the_contact_wildcard(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/loyalty/opt-in', [
            'contact_identifier' => 'regression@example.dz',
        ])->assertOk();

        $this->getJson('/api/v1/travel/loyalty/account?contact_identifier=regression@example.dz')
            ->assertOk()
            ->assertJsonPath('data.contact_identifier', 'regression@example.dz')
            ->assertJsonPath('data.opt_in', true)
            ->assertJsonPath('data.points_balance', 0);

        $this->getJson('/api/v1/travel/loyalty/entries?contact_identifier=regression@example.dz')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->getJson('/api/v1/travel/loyalty/rewards')
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
