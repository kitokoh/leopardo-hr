<?php

declare(strict_types=1);

namespace Tests\Feature\Hospitality;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HospitalityManager\Application\Actions\DeleteHospitalityPropertyAction;
use App\Modules\HospitalityManager\Application\Actions\DeleteHospitalityRoomTypeAction;
use App\Modules\HospitalityManager\Application\Actions\DeleteHospitalityUnitAction;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use App\Modules\HospitalityManager\Domain\Models\HospitalityRoomType;
use App\Modules\HospitalityManager\Domain\Models\HospitalityUnit;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Actions de suppression référentielle (BOS-024d, #8215) : suppression en
 * cascade silencieuse interdite — 422 HOSPITALITY_PROPERTY_IN_USE /
 * HOSPITALITY_ROOM_TYPE_IN_USE / HOSPITALITY_UNIT_IN_USE tant que des
 * éléments actifs sont rattachés.
 */
class HospitalityReferentialDeleteActionsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private HospitalityProperty $property;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        $company->setFeature('hospitality', true);
        $company->save();
        $this->company = $company;

        $this->property = $this->createProperty($company);
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

    private function createRoomType(HospitalityProperty $property, string $code): HospitalityRoomType
    {
        /** @var HospitalityRoomType $roomType */
        $roomType = HospitalityRoomType::query()->withoutGlobalScopes()->create([
            'company_id' => $property->company_id,
            'property_id' => $property->getKey(),
            'code' => $code,
            'name' => 'Type '.$code,
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

    public function test_property_with_room_type_cannot_be_deleted(): void
    {
        $this->createRoomType($this->property, 'STD');

        try {
            app(DeleteHospitalityPropertyAction::class)->execute($this->property);
            $this->fail('Un établissement avec des types rattachés doit être refusé.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertStringContainsString('HOSPITALITY_PROPERTY_IN_USE', (string) $exception->getMessage());
        }
    }

    public function test_empty_property_is_deleted(): void
    {
        app(DeleteHospitalityPropertyAction::class)->execute($this->property);

        $this->assertNull(HospitalityProperty::query()->withoutGlobalScopes()->find($this->property->getKey()));
    }

    public function test_room_type_with_units_cannot_be_deleted(): void
    {
        $roomType = $this->createRoomType($this->property, 'STD');
        $this->createUnit($this->property, $roomType, 'STD-101');

        try {
            app(DeleteHospitalityRoomTypeAction::class)->execute($roomType);
            $this->fail('Un type avec des unités doit être refusé.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertStringContainsString('HOSPITALITY_ROOM_TYPE_IN_USE', (string) $exception->getMessage());
        }
    }

    public function test_room_type_without_units_is_deleted(): void
    {
        $roomType = $this->createRoomType($this->property, 'STD');

        app(DeleteHospitalityRoomTypeAction::class)->execute($roomType);

        $this->assertNull(HospitalityRoomType::query()->withoutGlobalScopes()->find($roomType->getKey()));
    }

    public function test_unit_with_an_active_reservation_cannot_be_deleted(): void
    {
        $roomType = $this->createRoomType($this->property, 'STD');
        $unit = $this->createUnit($this->property, $roomType, 'STD-101');

        HospitalityReservation::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'reference' => 'HRS-'.fake()->unique()->bothify('????##'),
            'property_id' => $this->property->getKey(),
            'room_type_id' => $roomType->getKey(),
            'unit_id' => $unit->getKey(),
            'guest_name' => 'Client Test',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-04',
            'adults' => 1,
            'status' => HospitalityReservation::STATUS_CONFIRMED,
            'source' => HospitalityReservation::SOURCE_DESK,
        ]);

        try {
            app(DeleteHospitalityUnitAction::class)->execute($unit, (string) $this->company->id);
            $this->fail('Une unité avec une réservation active doit être refusée.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertStringContainsString('HOSPITALITY_UNIT_IN_USE', (string) $exception->getMessage());
        }
    }

    public function test_unit_with_only_terminal_reservations_is_deleted(): void
    {
        $roomType = $this->createRoomType($this->property, 'STD');
        $unit = $this->createUnit($this->property, $roomType, 'STD-101');

        HospitalityReservation::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'reference' => 'HRS-'.fake()->unique()->bothify('????##'),
            'property_id' => $this->property->getKey(),
            'room_type_id' => $roomType->getKey(),
            'unit_id' => $unit->getKey(),
            'guest_name' => 'Client Test',
            'check_in' => '2026-09-01',
            'check_out' => '2026-09-04',
            'adults' => 1,
            'status' => HospitalityReservation::STATUS_CHECKED_OUT,
            'source' => HospitalityReservation::SOURCE_DESK,
        ]);

        app(DeleteHospitalityUnitAction::class)->execute($unit, (string) $this->company->id);

        $this->assertNull(HospitalityUnit::query()->withoutGlobalScopes()->find($unit->getKey()));
    }
}
