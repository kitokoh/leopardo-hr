<?php

declare(strict_types=1);

namespace Tests\Feature\Hospitality;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HospitalityManager\Application\Actions\CreateDeskReservationAction;
use App\Modules\HospitalityManager\Application\Actions\TransitionHospitalityReservationAction;
use App\Modules\HospitalityManager\Application\Actions\UpdateHospitalityReservationAction;
use App\Modules\HospitalityManager\Domain\Exceptions\HospitalityInvalidTransitionException;
use App\Modules\HospitalityManager\Domain\Exceptions\HospitalityNoAvailabilityException;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use App\Modules\HospitalityManager\Domain\Models\HospitalityRoomType;
use App\Modules\HospitalityManager\Domain\Models\HospitalityUnit;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Actions des réservations guichet (BOS-024d, #8215) : création
 * anti-overbooking transactionnelle + idempotente, modification,
 * machine à états des transitions (409 hors graphe).
 */
class HospitalityReservationActionsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private HospitalityProperty $property;

    private HospitalityRoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        $company->setFeature('hospitality', true);
        $company->save();
        $this->company = $company;

        $this->property = $this->createProperty($company);
        $this->roomType = $this->createRoomType($this->property, 'STD', 'Standard');
        // Une unité opérationnelle = capacité 1 sur l'intervalle (sans
        // unité, toute création est refusée — capacité nulle).
        $this->createUnit($this->property, $this->roomType, 'STD-101');
    }

    private function createProperty(Company $company): HospitalityProperty
    {
        /** @var HospitalityProperty $property */
        $property = HospitalityProperty::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Hôtel Test',
            'code' => 'HTL-'.fake()->unique()->numerify('####'),
            'type' => 'hotel',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);

        return $property;
    }

    private function createRoomType(HospitalityProperty $property, string $code, string $name): HospitalityRoomType
    {
        /** @var HospitalityRoomType $roomType */
        $roomType = HospitalityRoomType::query()->withoutGlobalScopes()->create([
            'company_id' => $property->company_id,
            'property_id' => $property->getKey(),
            'code' => $code,
            'name' => $name,
            'base_price_minor' => 12000,
            'currency' => 'EUR',
        ]);

        return $roomType;
    }

    private function createUnit(HospitalityProperty $property, HospitalityRoomType $roomType, string $code): HospitalityUnit
    {
        /** @var HospitalityUnit $unit */
        $unit = HospitalityUnit::query()->withoutGlobalScopes()->create([
            'company_id' => $property->company_id,
            'property_id' => $property->getKey(),
            'room_type_id' => $roomType->getKey(),
            'code' => $code,
            'status' => 'available',
        ]);

        return $unit;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'property_id' => $this->property->getKey(),
            'room_type_id' => $this->roomType->getKey(),
            'guest_name' => 'Awa Ndiaye',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-04',
            'adults' => 2,
            'total_amount_minor' => 36000,
            'currency' => 'EUR',
        ], $overrides);
    }

    public function test_create_desk_reservation_generates_reference_and_pending_status(): void
    {
        $reservation = app(CreateDeskReservationAction::class)->execute(
            (string) $this->company->id,
            $this->payload(),
        );

        $this->assertTrue($reservation->wasRecentlyCreated);
        $this->assertSame(HospitalityReservation::STATUS_PENDING, $reservation->status);
        $this->assertSame(HospitalityReservation::SOURCE_DESK, $reservation->source);
        $this->assertNotSame('', $reservation->reference);
    }

    public function test_create_is_idempotent_on_idempotency_key(): void
    {
        $action = app(CreateDeskReservationAction::class);
        $first = $action->execute((string) $this->company->id, $this->payload(['idempotency_key' => 'desk-abc-123']));
        $replayed = $action->execute((string) $this->company->id, $this->payload(['idempotency_key' => 'desk-abc-123']));

        $this->assertSame($first->getKey(), $replayed->getKey());
        $this->assertFalse($replayed->wasRecentlyCreated);
        $this->assertSame(1, HospitalityReservation::query()->withoutGlobalScopes()->count());
    }

    public function test_create_beyond_capacity_is_rejected(): void
    {
        // Une seule unité opérationnelle : une seule réservation active par
        // intervalle (anti-overbooking).
        $action = app(CreateDeskReservationAction::class);
        $action->execute((string) $this->company->id, $this->payload());

        $this->expectException(HospitalityNoAvailabilityException::class);
        $action->execute((string) $this->company->id, $this->payload(['check_in' => '2026-10-03', 'check_out' => '2026-10-06']));
    }

    public function test_create_on_a_free_interval_is_allowed(): void
    {
        $action = app(CreateDeskReservationAction::class);
        $action->execute((string) $this->company->id, $this->payload());

        $second = $action->execute((string) $this->company->id, $this->payload(['check_in' => '2026-10-04', 'check_out' => '2026-10-07']));

        $this->assertTrue($second->wasRecentlyCreated);
    }

    public function test_update_applies_changes(): void
    {
        $reservation = app(CreateDeskReservationAction::class)->execute(
            (string) $this->company->id,
            $this->payload(),
        );

        $updated = app(UpdateHospitalityReservationAction::class)->execute($reservation, [
            'guest_name' => 'Moussa Diop',
            'adults' => 3,
        ]);

        $this->assertSame('Moussa Diop', $updated->guest_name);
        $this->assertSame(3, $updated->adults);
    }

    public function test_transition_follows_the_state_machine(): void
    {
        $reservation = app(CreateDeskReservationAction::class)->execute(
            (string) $this->company->id,
            $this->payload(),
        );

        $transition = app(TransitionHospitalityReservationAction::class);
        $confirmed = $transition->execute($reservation, HospitalityReservation::STATUS_CONFIRMED);
        $this->assertSame(HospitalityReservation::STATUS_CONFIRMED, $confirmed->status);

        $checkedIn = $transition->execute($confirmed, HospitalityReservation::STATUS_CHECKED_IN);
        $checkedOut = $transition->execute($checkedIn, HospitalityReservation::STATUS_CHECKED_OUT);
        $this->assertSame(HospitalityReservation::STATUS_CHECKED_OUT, $checkedOut->status);
    }

    public function test_transition_outside_the_state_machine_is_rejected(): void
    {
        $reservation = app(CreateDeskReservationAction::class)->execute(
            (string) $this->company->id,
            $this->payload(),
        );

        $this->expectException(HospitalityInvalidTransitionException::class);
        app(TransitionHospitalityReservationAction::class)->execute($reservation, HospitalityReservation::STATUS_CHECKED_OUT);
    }
}
