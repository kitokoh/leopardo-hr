<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\FarmOs;

use App\Modules\FarmOs\Domain\Enums\FarmBreakdownSeverity;
use App\Modules\FarmOs\Domain\Enums\FarmEquipmentKind;
use App\Modules\FarmOs\Domain\Enums\FarmEquipmentMeterType;
use App\Modules\FarmOs\Domain\Enums\FarmEquipmentStatus;
use App\Modules\FarmOs\Domain\Enums\FarmMaintenanceStatus;
use App\Modules\FarmOs\Domain\Enums\FarmMaintenanceType;
use App\Modules\FarmOs\Domain\Enums\FarmMeterSource;
use App\Modules\FarmOs\Domain\Exceptions\FarmEquipmentException;
use App\Modules\FarmOs\Domain\Models\FarmEquipmentDocument;
use PHPUnit\Framework\TestCase;

/**
 * Farm OS — T2.2 : contrats du registre de materiel agricole.
 *
 * Tests SANS base de donnees : ils verrouillent les invariants qui ne dependent
 * pas de PostgreSQL (registres fermes, absence de telemetrie, codes d'erreur
 * stables, absence de libelle en dur). Le comportement transactionnel complet
 * (verrouillage, refus de compteur qui recule, idempotence) est couvert par le
 * test Feature, qui exige PostgreSQL et ne tourne donc qu'en CI.
 */
final class FarmEquipmentContractTest extends TestCase
{
    public function test_equipment_kind_registry_is_closed_and_stable(): void
    {
        $this->assertSame([
            'tractor',
            'harvester',
            'seeder',
            'sprayer',
            'trailer',
            'truck',
            'car',
            'irrigation_system',
            'milking_machine',
            'tool',
            'other',
        ], FarmEquipmentKind::values());
    }

    public function test_equipment_status_registry_is_closed_and_stable(): void
    {
        $this->assertSame(['active', 'maintenance', 'out_of_service', 'retired'], FarmEquipmentStatus::values());
    }

    public function test_meter_type_registry_is_closed_and_stable(): void
    {
        $this->assertSame(['hours', 'kilometers', 'hectares', 'none'], FarmEquipmentMeterType::values());
    }

    public function test_maintenance_and_breakdown_registries_are_closed_and_stable(): void
    {
        $this->assertSame(['preventive', 'corrective'], FarmMaintenanceType::values());
        $this->assertSame(['planned', 'done', 'cancelled'], FarmMaintenanceStatus::values());
        $this->assertSame(['minor', 'major', 'critical'], FarmBreakdownSeverity::values());
    }

    /**
     * EXIGENCE D'HONNETETE CENTRALE : Farm OS n'a AUCUNE integration de
     * telemetrie agricole. Le registre des sources de compteur ne doit donc
     * jamais contenir de case `sensor` / `telemetry` / `gps` — sinon une valeur
     * pourrait etre presentee comme automatiquement mesuree.
     */
    public function test_meter_source_registry_has_no_telemetry_case(): void
    {
        $this->assertSame(['manual', 'imported'], FarmMeterSource::values());

        foreach (FarmMeterSource::values() as $value) {
            $this->assertStringNotContainsString('sensor', $value);
            $this->assertStringNotContainsString('telemetry', $value);
            $this->assertStringNotContainsString('gps', $value);
            $this->assertStringNotContainsString('auto', $value);
        }
    }

    public function test_meter_type_none_is_a_first_class_value(): void
    {
        // Un outil sans compteur n'est pas un outil incomplet : `none` est une
        // valeur de plein droit, et n'est jamais « suivi ».
        $this->assertFalse(FarmEquipmentMeterType::None->isTracked());
        $this->assertTrue(FarmEquipmentMeterType::Hours->isTracked());
        $this->assertTrue(FarmEquipmentMeterType::Kilometers->isTracked());
        $this->assertTrue(FarmEquipmentMeterType::Hectares->isTracked());
    }

    public function test_default_meter_type_is_only_a_suggestion(): void
    {
        // Le type par defaut est PROPOSE a la saisie, jamais impose : il sert a
        // pre-remplir un formulaire, pas a deduire une mesure reelle.
        $this->assertSame(FarmEquipmentMeterType::Hours, FarmEquipmentKind::Tractor->defaultMeterType());
        $this->assertSame(FarmEquipmentMeterType::Kilometers, FarmEquipmentKind::Truck->defaultMeterType());
        $this->assertSame(FarmEquipmentMeterType::None, FarmEquipmentKind::Tool->defaultMeterType());
    }

    public function test_retired_equipment_accepts_no_new_usage(): void
    {
        $this->assertFalse(FarmEquipmentStatus::Retired->acceptsUsage());
        $this->assertTrue(FarmEquipmentStatus::Active->acceptsUsage());
        $this->assertTrue(FarmEquipmentStatus::Maintenance->acceptsUsage());
        $this->assertTrue(FarmEquipmentStatus::OutOfService->acceptsUsage());
    }

    public function test_maintenance_status_terminality(): void
    {
        // Un entretien clos n'est jamais rouvert : on en planifie un nouveau.
        $this->assertFalse(FarmMaintenanceStatus::Planned->isTerminal());
        $this->assertTrue(FarmMaintenanceStatus::Done->isTerminal());
        $this->assertTrue(FarmMaintenanceStatus::Cancelled->isTerminal());
    }

    public function test_document_type_registry_is_closed_and_stable(): void
    {
        $this->assertSame(
            ['registration', 'insurance', 'invoice', 'manual', 'inspection', 'other'],
            FarmEquipmentDocument::TYPES,
        );
    }

    public function test_translation_keys_are_namespaced_and_ascii(): void
    {
        foreach (FarmEquipmentKind::cases() as $kind) {
            $this->assertStringStartsWith('farm.equipment_kind.', $kind->translationKey());
        }

        foreach (FarmEquipmentStatus::cases() as $status) {
            $this->assertStringStartsWith('farm.equipment_status.', $status->translationKey());
        }

        foreach (FarmEquipmentMeterType::cases() as $type) {
            $this->assertStringStartsWith('farm.meter_type.', $type->translationKey());
        }

        foreach (FarmMeterSource::cases() as $source) {
            $this->assertStringStartsWith('farm.meter_source.', $source->translationKey());
        }

        foreach (FarmMaintenanceType::cases() as $type) {
            $this->assertStringStartsWith('farm.maintenance_type.', $type->translationKey());
        }

        foreach (FarmMaintenanceStatus::cases() as $status) {
            $this->assertStringStartsWith('farm.maintenance_status.', $status->translationKey());
        }

        foreach (FarmBreakdownSeverity::cases() as $severity) {
            $this->assertStringStartsWith('farm.breakdown_severity.', $severity->translationKey());
        }
    }

    public function test_equipment_exception_exposes_stable_ascii_error_codes(): void
    {
        $cases = [
            [FarmEquipmentException::equipmentNotInCompany(7), 'FARM_EQUIPMENT_NOT_IN_COMPANY'],
            [FarmEquipmentException::meterRegression('120.00', '80.00'), 'FARM_EQUIPMENT_METER_REGRESSION'],
            [FarmEquipmentException::meterNotTracked(3), 'FARM_EQUIPMENT_METER_NOT_TRACKED'],
            [FarmEquipmentException::meterTypeMismatch('hours', 'kilometers'), 'FARM_EQUIPMENT_METER_TYPE_MISMATCH'],
            [FarmEquipmentException::maintenanceAlreadyClosed(11), 'FARM_EQUIPMENT_MAINTENANCE_ALREADY_CLOSED'],
            [FarmEquipmentException::equipmentRetired(5), 'FARM_EQUIPMENT_RETIRED'],
            [FarmEquipmentException::invalidCost('-1'), 'FARM_EQUIPMENT_INVALID_COST'],
        ];

        foreach ($cases as [$exception, $expectedCode]) {
            $this->assertSame($expectedCode, $exception->errorCode());
            // Garde i18n (PA2-I18N-007 / #5432) : jamais de libelle accentue.
            $this->assertSame(0, preg_match('/[^\x00-\x7F]/', $exception->errorCode()));
        }
    }
}
