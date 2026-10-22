<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\FarmOs;

use App\Modules\FarmOs\Domain\Enums\FarmStorageLocationType;
use App\Modules\FarmOs\Domain\Enums\FarmStorageMovementType;
use App\Modules\FarmOs\Domain\Exceptions\FarmHarvestException;
use App\Modules\FarmOs\Domain\Models\FarmHarvestLot;
use PHPUnit\Framework\TestCase;

/**
 * Farm OS — T3.2 : contrats des recoltes, de l'entreposage et de la tracabilite.
 *
 * Tests SANS base de donnees : ils verrouillent les invariants qui ne dependent
 * pas de PostgreSQL (registres fermes, sens des mouvements, codes d'erreur
 * stables, absence de libelle en dur). Le rapprochement recolte / stock / vente
 * est couvert par le test Feature, qui exige PostgreSQL et ne tourne qu'en CI.
 */
final class FarmHarvestContractTest extends TestCase
{
    public function test_storage_location_type_registry_is_closed_and_stable(): void
    {
        $this->assertSame([
            'barn',
            'silo',
            'warehouse',
            'cold_room',
            'tank',
            'field_stack',
            'other',
        ], FarmStorageLocationType::values());
    }

    public function test_movement_type_registry_is_closed_and_stable(): void
    {
        $this->assertSame(['in', 'transfer', 'loss', 'adjustment', 'sale', 'consumption'], FarmStorageMovementType::values());
    }

    public function test_lot_status_registry_is_closed_and_stable(): void
    {
        $this->assertSame(['open', 'closed'], FarmHarvestLot::STATUSES);
    }

    /**
     * Le SENS d'un mouvement est porte par le TYPE, jamais par le signe d'une
     * quantite : c'est ce qui rend impossible de « sortir en negatif » pour
     * contourner la garde de stock.
     */
    public function test_outbound_direction_is_carried_by_the_type(): void
    {
        $this->assertTrue(FarmStorageMovementType::Loss->isOutbound());
        $this->assertTrue(FarmStorageMovementType::Sale->isOutbound());
        $this->assertTrue(FarmStorageMovementType::Consumption->isOutbound());

        $this->assertFalse(FarmStorageMovementType::In->isOutbound());
        // Un TRANSFERT ne retire rien : la marchandise change d'emplacement,
        // elle reste dans le lot. Sinon un demenagement ferait disparaitre du
        // stock, ce qui casserait le rapprochement recolte / stock / vente.
        $this->assertFalse(FarmStorageMovementType::Transfer->isOutbound());
        // Un ajustement est traite a part (il peut aller dans les deux sens).
        $this->assertFalse(FarmStorageMovementType::Adjustment->isOutbound());
    }

    /**
     * Une vente doit referencer un document du service de VENTES EXISTANT :
     * Farm OS ne cree aucun document commercial et aucun systeme financier
     * parallele.
     */
    public function test_only_sale_requires_a_reference_to_the_existing_sales_service(): void
    {
        $this->assertTrue(FarmStorageMovementType::Sale->requiresSaleReference());

        foreach ([FarmStorageMovementType::In, FarmStorageMovementType::Transfer, FarmStorageMovementType::Loss, FarmStorageMovementType::Adjustment, FarmStorageMovementType::Consumption] as $type) {
            $this->assertFalse($type->requiresSaleReference());
        }
    }

    public function test_translation_keys_are_namespaced(): void
    {
        foreach (FarmStorageLocationType::cases() as $case) {
            $this->assertStringStartsWith('farm.storage_location_type.', $case->translationKey());
        }

        foreach (FarmStorageMovementType::cases() as $case) {
            $this->assertStringStartsWith('farm.storage_movement_type.', $case->translationKey());
        }
    }

    public function test_harvest_exception_exposes_stable_ascii_error_codes(): void
    {
        $cases = [
            [FarmHarvestException::lotNotInCompany(1), 'FARM_HARVEST_LOT_NOT_IN_COMPANY'],
            [FarmHarvestException::locationNotInCompany(2), 'FARM_HARVEST_LOCATION_NOT_IN_COMPANY'],
            [FarmHarvestException::quantityMustBePositive('0'), 'FARM_HARVEST_QUANTITY_NOT_POSITIVE'],
            [FarmHarvestException::insufficientStock(3, '10.000', '20.000'), 'FARM_HARVEST_INSUFFICIENT_STOCK'],
            [FarmHarvestException::lotAlreadyClosed(4), 'FARM_HARVEST_LOT_ALREADY_CLOSED'],
            [FarmHarvestException::saleReferenceRequired(), 'FARM_HARVEST_SALE_REFERENCE_REQUIRED'],
            [FarmHarvestException::invalidQuantity('x'), 'FARM_HARVEST_INVALID_QUANTITY'],
        ];

        foreach ($cases as [$exception, $expectedCode]) {
            $this->assertSame($expectedCode, $exception->errorCode());
            // Garde i18n (PA2-I18N-007 / #5432) : jamais de libelle accentue.
            $this->assertSame(0, preg_match('/[^\x00-\x7F]/', $exception->errorCode()));
        }
    }
}
