<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\FarmOs;

use App\Modules\FarmOs\Domain\Enums\FarmInputCategory;
use App\Modules\FarmOs\Domain\Enums\FarmInputUnit;
use App\Modules\FarmOs\Domain\Enums\FarmStockMovementType;
use App\Modules\FarmOs\Domain\Exceptions\FarmStockException;
use PHPUnit\Framework\TestCase;

/**
 * Farm OS — T2.1 : contrats du registre d'intrants et du journal de stock.
 *
 * Tests SANS base de données : ils verrouillent les invariants qui ne dépendent
 * pas de PostgreSQL (registres fermés, sens du mouvement, codes d'erreur
 * stables, absence de libellé en dur). Le comportement transactionnel complet
 * (verrouillage, refus de stock négatif, idempotence) est couvert par les tests
 * Feature, qui exigent PostgreSQL et ne tournent donc qu'en CI.
 */
final class FarmInputStockContractTest extends TestCase
{
    public function test_input_category_registry_is_closed_and_stable(): void
    {
        $this->assertSame([
            'seeds',
            'fertilizers',
            'plant_protection',
            'animal_feed',
            'veterinary',
            'spare_parts',
            'fuel',
            'other',
        ], FarmInputCategory::values());
    }

    public function test_input_unit_registry_is_closed_and_stable(): void
    {
        $this->assertSame(['kg', 'g', 'l', 'ml', 'unit', 'bag', 'dose', 't'], FarmInputUnit::values());
    }

    public function test_movement_type_registry_is_closed_and_stable(): void
    {
        $this->assertSame(['receipt', 'issue', 'adjustment'], FarmStockMovementType::values());
    }

    public function test_movement_direction_is_carried_by_type_not_by_sign(): void
    {
        $this->assertTrue(FarmStockMovementType::Receipt->increasesStock());
        $this->assertFalse(FarmStockMovementType::Issue->increasesStock());
        // Un ajustement est ambigu par nature : c'est `quantity_after` qui
        // tranche, jamais le type.
        $this->assertNull(FarmStockMovementType::Adjustment->increasesStock());
    }

    public function test_regulated_categories_are_flagged_by_default(): void
    {
        // Farm OS ne fournit aucun conseil de dosage : il se contente de
        // signaler les catégories réglementées.
        $this->assertTrue(FarmInputCategory::PlantProtection->isRegulatedByDefault());
        $this->assertTrue(FarmInputCategory::Veterinary->isRegulatedByDefault());
        $this->assertFalse(FarmInputCategory::Seeds->isRegulatedByDefault());
        $this->assertFalse(FarmInputCategory::SpareParts->isRegulatedByDefault());
    }

    public function test_translation_keys_are_namespaced_and_ascii(): void
    {
        foreach (FarmInputCategory::cases() as $category) {
            $this->assertStringStartsWith('farm.input_category.', $category->translationKey());
        }

        foreach (FarmInputUnit::cases() as $unit) {
            $this->assertStringStartsWith('farm.input_unit.', $unit->translationKey());
        }

        foreach (FarmStockMovementType::cases() as $type) {
            $this->assertStringStartsWith('farm.stock_movement.', $type->translationKey());
        }
    }

    public function test_stock_exception_exposes_stable_ascii_error_codes(): void
    {
        $cases = [
            [FarmStockException::quantityMustBePositive('0'), 'FARM_STOCK_QUANTITY_NOT_POSITIVE'],
            [FarmStockException::insufficientStock(3, '1.000', '5.000'), 'FARM_STOCK_INSUFFICIENT'],
            [FarmStockException::lotNotInCompany(9), 'FARM_STOCK_LOT_NOT_IN_COMPANY'],
            [FarmStockException::inputNotInCompany(4), 'FARM_STOCK_INPUT_NOT_IN_COMPANY'],
            [FarmStockException::lotRequiredForIssue(), 'FARM_STOCK_LOT_REQUIRED_FOR_ISSUE'],
        ];

        foreach ($cases as [$exception, $expectedCode]) {
            $this->assertSame($expectedCode, $exception->errorCode());
            // Garde i18n (PA2-I18N-007 / #5432) : jamais de libellé accentué.
            $this->assertSame(0, preg_match('/[^\x00-\x7F]/', $exception->errorCode()));
        }
    }
}
