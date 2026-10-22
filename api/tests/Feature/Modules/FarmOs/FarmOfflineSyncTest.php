<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FarmOs;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmConflictStrategy;
use App\Modules\FarmOs\Domain\Enums\FarmOfflineOperationType;
use App\Modules\FarmOs\Domain\Enums\FarmSyncStatus;
use App\Modules\FarmOs\Domain\Exceptions\FarmSyncException;
use App\Modules\FarmOs\Domain\Models\FarmSyncConflict;
use App\Modules\FarmOs\Domain\Services\FarmSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Farm OS — T5.2 : comportement REEL de la synchronisation hors ligne.
 *
 * Ces tests exercent le service metier sur un schema tenant. Ils exigent
 * PostgreSQL et ne tournent donc qu'en CI.
 *
 * INVARIANTS VERIFIES ICI :
 *   1. IDEMPOTENCE — le rejeu d'une operation deja appliquee ne re-applique rien ;
 *   2. CONFLIT D'IDENTIFIANT — meme identifiant + contenu different = conflit ;
 *   3. AUCUN ECRASEMENT SILENCIEUX — stock et finances partent en arbitrage ;
 *   4. HISTORIQUE DES ERREURS — une operation refusee reste visible.
 */
final class FarmOfflineSyncTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        app()->instance('current_company', $this->company);
    }

    public function test_a_new_operation_is_recorded_as_pending(): void
    {
        $outcome = $this->submit('op-1', FarmOfflineOperationType::RecordObservation, ['note' => 'aphids seen']);

        $this->assertFalse($outcome['replayed']);
        $this->assertSame(FarmSyncStatus::Pending->value, $outcome['operation']->status);
        $this->assertNull($outcome['conflict']);
    }

    /**
     * IDEMPOTENCE CRITIQUE : le rejeu d'une operation deja recue ne doit RIEN
     * re-appliquer. Sans cette garantie, une mortalite ou une vente saisie deux
     * fois hors reseau serait comptee deux fois.
     */
    public function test_replaying_the_same_operation_is_idempotent(): void
    {
        $service = app(FarmSyncService::class);

        $first = $this->submit('op-replay', FarmOfflineOperationType::RecordObservation, ['note' => 'same']);
        $second = $this->submit('op-replay', FarmOfflineOperationType::RecordObservation, ['note' => 'same']);

        $this->assertTrue($second['replayed']);
        $this->assertSame($first['operation']->id, $second['operation']->id);
        $this->assertSame(1, DB::table('farm_sync_operations')->where('company_id', $this->company->id)->count());
        $this->assertSame(0, $service->statusSummary($this->company)['conflict']);
    }

    /**
     * MEME identifiant + contenu DIFFERENT n'est pas un rejeu : c'est un conflit
     * d'identifiant, et il est conserve pour arbitrage.
     */
    public function test_same_identifier_with_different_payload_is_a_conflict(): void
    {
        $this->submit('op-mismatch', FarmOfflineOperationType::RecordObservation, ['note' => 'first']);
        $second = $this->submit('op-mismatch', FarmOfflineOperationType::RecordObservation, ['note' => 'second']);

        $this->assertFalse($second['replayed']);
        $this->assertInstanceOf(FarmSyncConflict::class, $second['conflict']);
        $this->assertSame(FarmSyncStatus::Conflict->value, $second['operation']->status);
        $this->assertSame('FARM_SYNC_PAYLOAD_MISMATCH', $second['operation']->last_error);
    }

    public function test_an_empty_client_operation_id_is_refused(): void
    {
        $this->expectException(FarmSyncException::class);

        app(FarmSyncService::class)->submit(
            $this->company,
            '   ',
            FarmOfflineOperationType::RecordObservation,
            ['note' => 'x'],
            Carbon::now(),
        );
    }

    /**
     * Une operation hors ligne NON PREVUE est refusee et conservee : Farm OS ne
     * promet pas une fonction hors ligne qu'il n'implemente pas.
     */
    public function test_an_unsupported_operation_is_rejected_and_kept(): void
    {
        $rejected = app(FarmSyncService::class)->rejectUnsupported(
            $this->company,
            'op-unsupported',
            'issue_invoice',
            ['amount' => '100.00'],
            Carbon::now(),
        );

        $this->assertSame(FarmSyncStatus::Rejected->value, $rejected->status);
        $this->assertSame('FARM_SYNC_UNSUPPORTED_OPERATION', $rejected->last_error);
        // Elle reste VISIBLE dans l'historique des erreurs.
        $this->assertCount(1, app(FarmSyncService::class)->errors($this->company));
    }

    /**
     * Les operations touchant le STOCK ou les FINANCES ne sont jamais tranchees
     * automatiquement : elles exigent un arbitrage humain.
     */
    public function test_stock_and_finance_operations_are_routed_to_manual_review(): void
    {
        $service = app(FarmSyncService::class);

        $this->assertSame(FarmConflictStrategy::ManualReview, $service->strategyFor(FarmOfflineOperationType::RecordInputMovement));
        $this->assertSame(FarmConflictStrategy::ManualReview, $service->strategyFor(FarmOfflineOperationType::RecordHarvestMovement));
        $this->assertSame(FarmConflictStrategy::ManualReview, $service->strategyFor(FarmOfflineOperationType::RecordExpense));

        // Une observation est additive : applicable localement.
        $this->assertSame(FarmConflictStrategy::LocalWins, $service->strategyFor(FarmOfflineOperationType::RecordObservation));
    }

    public function test_marking_an_operation_applied_records_the_server_entity(): void
    {
        $service = app(FarmSyncService::class);
        $outcome = $this->submit('op-apply', FarmOfflineOperationType::RecordObservation, ['note' => 'x']);

        $applied = $service->markApplied($this->company, $outcome['operation'], 'farm_observations', 42);

        $this->assertSame(FarmSyncStatus::Applied->value, $applied->status);
        $this->assertSame('farm_observations', $applied->applied_entity_type);
        $this->assertSame(42, $applied->applied_entity_id);
        $this->assertNotNull($applied->applied_at);
    }

    /**
     * Une operation en echec reste visible et n'est rejouee qu'un nombre borne
     * de fois : au-dela, elle passe en echec definitif plutot que de boucler.
     */
    public function test_a_failing_operation_stays_visible_and_stops_retrying(): void
    {
        $service = app(FarmSyncService::class);
        $outcome = $this->submit('op-fail', FarmOfflineOperationType::RecordObservation, ['note' => 'x']);
        $operation = $outcome['operation'];

        for ($i = 0; $i < FarmSyncService::MAX_ATTEMPTS; $i++) {
            $operation = $service->markFailed($this->company, $operation, 'FARM_SYNC_APPLY_FAILED');
        }

        $this->assertSame(FarmSyncStatus::Failed->value, $operation->status);
        $this->assertSame(FarmSyncService::MAX_ATTEMPTS, $operation->attempt_count);
        $this->assertCount(1, $service->errors($this->company));
    }

    public function test_a_conflict_can_be_resolved_explicitly_by_a_human(): void
    {
        $service = app(FarmSyncService::class);

        $this->submit('op-c', FarmOfflineOperationType::RecordObservation, ['note' => 'first']);
        $second = $this->submit('op-c', FarmOfflineOperationType::RecordObservation, ['note' => 'second']);

        $conflict = $second['conflict'];
        $this->assertInstanceOf(FarmSyncConflict::class, $conflict);
        $this->assertCount(1, $service->unresolvedConflicts($this->company));

        $resolved = $service->resolveConflict($this->company, $conflict, FarmConflictStrategy::ServerWins, 7, 'kept server value');

        $this->assertSame('server_wins', $resolved->resolution);
        $this->assertSame(7, $resolved->resolved_by);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertCount(0, $service->unresolvedConflicts($this->company));
    }

    /**
     * Un conflit deja resolu n'est jamais re-resolu : la decision initiale reste
     * auditable.
     */
    public function test_a_resolved_conflict_cannot_be_resolved_again(): void
    {
        $service = app(FarmSyncService::class);

        $this->submit('op-d', FarmOfflineOperationType::RecordObservation, ['note' => 'first']);
        $second = $this->submit('op-d', FarmOfflineOperationType::RecordObservation, ['note' => 'second']);

        /** @var FarmSyncConflict $conflict */
        $conflict = $second['conflict'];
        $service->resolveConflict($this->company, $conflict, FarmConflictStrategy::LocalWins);

        $this->expectException(FarmSyncException::class);

        $service->resolveConflict($this->company, $conflict->refresh(), FarmConflictStrategy::ServerWins);
    }

    public function test_status_summary_reports_every_queue_state(): void
    {
        $service = app(FarmSyncService::class);

        $this->submit('op-s1', FarmOfflineOperationType::RecordObservation, ['note' => 'a']);
        $applied = $this->submit('op-s2', FarmOfflineOperationType::RecordObservation, ['note' => 'b']);
        $service->markApplied($this->company, $applied['operation']);
        $service->rejectUnsupported($this->company, 'op-s3', 'issue_invoice', [], Carbon::now());

        $summary = $service->statusSummary($this->company);

        $this->assertSame(1, $summary['pending']);
        $this->assertSame(1, $summary['applied']);
        $this->assertSame(1, $summary['rejected']);
        $this->assertSame(0, $summary['conflict']);
        $this->assertSame(0, $summary['failed']);
    }

    public function test_payload_hash_is_stable_regardless_of_key_order(): void
    {
        $service = app(FarmSyncService::class);

        $this->assertSame(
            $service->payloadHash(['a' => 1, 'b' => 2]),
            $service->payloadHash(['b' => 2, 'a' => 1]),
        );
        $this->assertNotSame(
            $service->payloadHash(['a' => 1]),
            $service->payloadHash(['a' => 2]),
        );
    }

    public function test_sync_data_is_isolated_between_tenants(): void
    {
        $other = Company::factory()->create();

        $this->submit('op-iso', FarmOfflineOperationType::RecordObservation, ['note' => 'x']);

        $this->assertSame(1, DB::table('farm_sync_operations')->where('company_id', $this->company->id)->count());
        $this->assertSame(0, DB::table('farm_sync_operations')->where('company_id', $other->id)->count());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{operation: \App\Modules\FarmOs\Domain\Models\FarmSyncOperation, replayed: bool, conflict: FarmSyncConflict|null}
     */
    private function submit(string $clientOperationId, FarmOfflineOperationType $type, array $payload): array
    {
        return app(FarmSyncService::class)->submit(
            $this->company,
            $clientOperationId,
            $type,
            $payload,
            Carbon::now(),
        );
    }
}
