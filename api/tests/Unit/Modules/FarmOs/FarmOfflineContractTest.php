<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\FarmOs;

use App\Modules\FarmOs\Domain\Enums\FarmConflictStrategy;
use App\Modules\FarmOs\Domain\Enums\FarmOfflineOperationType;
use App\Modules\FarmOs\Domain\Enums\FarmSyncStatus;
use App\Modules\FarmOs\Domain\Exceptions\FarmSyncException;
use PHPUnit\Framework\TestCase;

/**
 * Farm OS — T5.2 : contrats du mode hors ligne.
 *
 * Tests SANS base de donnees : ils verrouillent les invariants qui ne dependent
 * pas de PostgreSQL (registre ferme d'operations, strategie de conflit, codes
 * d'erreur stables, absence de libelle en dur). Le comportement transactionnel
 * (idempotence, historique d'erreurs) est couvert par le test Feature.
 */
final class FarmOfflineContractTest extends TestCase
{
    /**
     * Le registre est FERME : c'est la liste exacte de ce que Farm OS promet de
     * savoir faire sans reseau. Toute operation absente est refusee.
     */
    public function test_offline_operation_registry_is_closed_and_stable(): void
    {
        $this->assertSame([
            'record_observation',
            'record_operation',
            'record_input_movement',
            'record_harvest_movement',
            'record_livestock_event',
            'record_equipment_usage',
            'record_expense',
            'attach_photo',
        ], FarmOfflineOperationType::values());
    }

    /**
     * Aucune operation de facturation ou de paiement n'est hors ligne : elles
     * exigent le service de ventes existant, donc le reseau.
     */
    public function test_no_billing_or_payment_operation_is_available_offline(): void
    {
        foreach (FarmOfflineOperationType::values() as $value) {
            $this->assertStringNotContainsString('invoice', $value);
            $this->assertStringNotContainsString('payment', $value);
            $this->assertStringNotContainsString('delete', $value);
        }
    }

    public function test_sync_status_registry_is_closed_and_stable(): void
    {
        $this->assertSame(['pending', 'applied', 'conflict', 'rejected', 'failed'], FarmSyncStatus::values());
    }

    public function test_conflict_strategy_registry_is_closed_and_stable(): void
    {
        $this->assertSame(['local_wins', 'server_wins', 'manual_review'], FarmConflictStrategy::values());
    }

    /**
     * Une operation en echec ou refusee reste VISIBLE : elle n'est jamais
     * silencieusement perdue.
     */
    public function test_error_statuses_are_visible_in_the_error_history(): void
    {
        $this->assertTrue(FarmSyncStatus::Rejected->isError());
        $this->assertTrue(FarmSyncStatus::Failed->isError());
        $this->assertFalse(FarmSyncStatus::Applied->isError());
        $this->assertFalse(FarmSyncStatus::Pending->isError());
    }

    public function test_only_pending_is_non_terminal(): void
    {
        $this->assertFalse(FarmSyncStatus::Pending->isTerminal());
        $this->assertTrue(FarmSyncStatus::Applied->isTerminal());
        $this->assertTrue(FarmSyncStatus::Conflict->isTerminal());
        $this->assertTrue(FarmSyncStatus::Rejected->isTerminal());
        $this->assertTrue(FarmSyncStatus::Failed->isTerminal());
    }

    /**
     * Les operations touchant le STOCK ou les FINANCES exigent un arbitrage
     * humain : Farm OS refuse de trancher a la place de l'exploitant sur une
     * donnee qui fausserait un stock ou une marge.
     */
    public function test_stock_and_finance_operations_require_manual_review(): void
    {
        $this->assertTrue(FarmOfflineOperationType::RecordInputMovement->affectsStock());
        $this->assertTrue(FarmOfflineOperationType::RecordHarvestMovement->affectsStock());
        $this->assertTrue(FarmOfflineOperationType::RecordExpense->affectsFinance());

        $this->assertTrue(FarmConflictStrategy::ManualReview->requiresHumanArbitration());
        $this->assertFalse(FarmConflictStrategy::LocalWins->requiresHumanArbitration());
        $this->assertFalse(FarmConflictStrategy::ServerWins->requiresHumanArbitration());
    }

    /**
     * Les operations purement ADDITIVES ne peuvent pas ecraser une donnee :
     * elles sont les seules applicables localement sans arbitrage.
     */
    public function test_additive_operations_are_the_only_safe_ones(): void
    {
        $this->assertTrue(FarmOfflineOperationType::RecordObservation->isAdditive());
        $this->assertTrue(FarmOfflineOperationType::AttachPhoto->isAdditive());
        $this->assertTrue(FarmOfflineOperationType::RecordLivestockEvent->isAdditive());

        // Un mouvement de stock n'est PAS additif au sens de la resolution :
        // il modifie une quantite existante.
        $this->assertFalse(FarmOfflineOperationType::RecordInputMovement->isAdditive());
        $this->assertFalse(FarmOfflineOperationType::RecordExpense->isAdditive());
    }

    public function test_translation_keys_are_namespaced(): void
    {
        foreach (FarmOfflineOperationType::cases() as $case) {
            $this->assertStringStartsWith('farm.offline_operation.', $case->translationKey());
        }

        foreach (FarmSyncStatus::cases() as $case) {
            $this->assertStringStartsWith('farm.sync_status.', $case->translationKey());
        }

        foreach (FarmConflictStrategy::cases() as $case) {
            $this->assertStringStartsWith('farm.conflict_strategy.', $case->translationKey());
        }
    }

    public function test_sync_exception_exposes_stable_ascii_error_codes(): void
    {
        $cases = [
            [FarmSyncException::unsupportedOperation('x'), 'FARM_SYNC_UNSUPPORTED_OPERATION'],
            [FarmSyncException::missingClientOperationId(), 'FARM_SYNC_MISSING_CLIENT_OPERATION_ID'],
            [FarmSyncException::payloadMismatch('op-1'), 'FARM_SYNC_PAYLOAD_MISMATCH'],
            [FarmSyncException::operationNotInCompany(1), 'FARM_SYNC_OPERATION_NOT_IN_COMPANY'],
            [FarmSyncException::conflictAlreadyResolved(2), 'FARM_SYNC_CONFLICT_ALREADY_RESOLVED'],
        ];

        foreach ($cases as [$exception, $expectedCode]) {
            $this->assertSame($expectedCode, $exception->errorCode());
            // Garde i18n (PA2-I18N-007 / #5432) : jamais de libelle accentue.
            $this->assertSame(0, preg_match('/[^\x00-\x7F]/', $exception->errorCode()));
        }
    }
}
