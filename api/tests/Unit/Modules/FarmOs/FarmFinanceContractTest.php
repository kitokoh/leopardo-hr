<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\FarmOs;

use App\Modules\FarmOs\Domain\Enums\FarmCostAxis;
use App\Modules\FarmOs\Domain\Enums\FarmDataQuality;
use App\Modules\FarmOs\Domain\Enums\FarmExpenseCategory;
use App\Modules\FarmOs\Domain\Exceptions\FarmFinanceException;
use App\Modules\FarmOs\Domain\Models\FarmCampaign;
use PHPUnit\Framework\TestCase;

/**
 * Farm OS — T4.1 : contrats des achats, ventes, depenses et de la rentabilite.
 *
 * Tests SANS base de donnees : ils verrouillent les invariants qui ne dependent
 * pas de PostgreSQL (registres fermes, qualite de donnee, codes d'erreur stables,
 * absence de libelle en dur). Le calcul de rentabilite est couvert par le test
 * Feature, qui exige PostgreSQL et ne tourne qu'en CI.
 */
final class FarmFinanceContractTest extends TestCase
{
    public function test_expense_category_registry_is_closed_and_stable(): void
    {
        $this->assertSame([
            'seeds',
            'fertilizer',
            'phytosanitary',
            'feed',
            'veterinary',
            'labour',
            'equipment_maintenance',
            'fuel',
            'storage',
            'transport',
            'irrigation',
            'other',
        ], FarmExpenseCategory::values());
    }

    public function test_cost_axis_registry_is_closed_and_stable(): void
    {
        $this->assertSame(['farm', 'parcel', 'crop', 'livestock_batch', 'equipment', 'campaign'], FarmCostAxis::values());
    }

    public function test_campaign_status_registry_is_closed_and_stable(): void
    {
        $this->assertSame(['open', 'closed'], FarmCampaign::STATUSES);
    }

    /**
     * C'est l'enum qui porte l'exigence d'HONNETETE : une donnee manquante ne
     * peut pas etre additionnee a un total fiable, et n'est jamais remplacee par
     * zero.
     */
    public function test_data_quality_registry_separates_real_estimated_and_missing(): void
    {
        $this->assertSame(['real', 'estimated', 'missing'], FarmDataQuality::values());

        $this->assertTrue(FarmDataQuality::Real->isUsableInTotals());
        $this->assertTrue(FarmDataQuality::Estimated->isUsableInTotals());
        // Une donnee MANQUANTE n'est jamais utilisable dans un total.
        $this->assertFalse(FarmDataQuality::Missing->isUsableInTotals());
    }

    public function test_only_input_related_categories_offer_an_input_link(): void
    {
        $this->assertTrue(FarmExpenseCategory::Seeds->isInputRelated());
        $this->assertTrue(FarmExpenseCategory::Fertilizer->isInputRelated());
        $this->assertTrue(FarmExpenseCategory::Phytosanitary->isInputRelated());
        $this->assertTrue(FarmExpenseCategory::Feed->isInputRelated());
        $this->assertTrue(FarmExpenseCategory::Veterinary->isInputRelated());

        $this->assertFalse(FarmExpenseCategory::Labour->isInputRelated());
        $this->assertFalse(FarmExpenseCategory::Transport->isInputRelated());
    }

    public function test_translation_keys_are_namespaced(): void
    {
        foreach (FarmExpenseCategory::cases() as $case) {
            $this->assertStringStartsWith('farm.expense_category.', $case->translationKey());
        }

        foreach (FarmCostAxis::cases() as $case) {
            $this->assertStringStartsWith('farm.cost_axis.', $case->translationKey());
        }

        foreach (FarmDataQuality::cases() as $case) {
            $this->assertStringStartsWith('farm.data_quality.', $case->translationKey());
        }
    }

    public function test_finance_exception_exposes_stable_ascii_error_codes(): void
    {
        $cases = [
            [FarmFinanceException::campaignNotInCompany(1), 'FARM_FINANCE_CAMPAIGN_NOT_IN_COMPANY'],
            [FarmFinanceException::campaignClosed(2), 'FARM_FINANCE_CAMPAIGN_CLOSED'],
            [FarmFinanceException::invalidAmount('0'), 'FARM_FINANCE_INVALID_AMOUNT'],
            [FarmFinanceException::saleDocumentRequired(), 'FARM_FINANCE_SALE_DOCUMENT_REQUIRED'],
            [FarmFinanceException::saleTargetRequired(), 'FARM_FINANCE_SALE_TARGET_REQUIRED'],
            [FarmFinanceException::saleAlreadyLinked(3, 4), 'FARM_FINANCE_SALE_ALREADY_LINKED'],
        ];

        foreach ($cases as [$exception, $expectedCode]) {
            $this->assertSame($expectedCode, $exception->errorCode());
            // Garde i18n (PA2-I18N-007 / #5432) : jamais de libelle accentue.
            $this->assertSame(0, preg_match('/[^\x00-\x7F]/', $exception->errorCode()));
        }
    }
}
