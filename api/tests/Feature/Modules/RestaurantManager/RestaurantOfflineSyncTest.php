<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\RestaurantManager;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantConflictStrategy;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantOfflineOperationType;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantSyncStatus;
use App\Modules\RestaurantManager\Domain\Exceptions\RestaurantSyncException;
use App\Modules\RestaurantManager\Domain\Models\RestaurantSyncConflict;
use App\Modules\RestaurantManager\Domain\Services\RestaurantSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Restaurant OS — REST-OS-7 : comportement REEL de la synchronisation hors
 * ligne (#8609).
 *
 * Ces tests exercent le service metier sur un schema tenant. Ils exigent
 * PostgreSQL et ne tournent donc qu'en CI.
 *
 * INVARIANTS VERIFIES ICI (les criteres d'acceptation de l'issue) :
 *   1. REJEU IDEMPOTENT SANS DOUBLON — le rejeu d'une operation deja recue ne
 *      re-applique rien ;
 *   2. DEUX TERMINAUX DECONNECTES NE PEUVENT PAS CONFIRMER SILENCIEUSEMENT DES
 *      OPERATIONS INCOMPATIBLES — la seconde part en conflit, elle n'ecrase pas
 *      la premiere ;
 *   3. CONFLITS AFFICHES, RESOLUTION EXPLICITE — un conflit non resolu est
 *      visible et n'est jamais re-resolu ;
 *   4. HISTORIQUE DES ERREURS — une operation refusee reste visible.
 */
final class RestaurantOfflineSyncTest extends TestCase
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
        $outcome = $this->submit('op-1', RestaurantOfflineOperationType::RecordOrder, ['total_minor' => 1200]);

        $this->assertFalse($outcome['replayed']);
        $this->assertSame(RestaurantSyncStatus::Pending->value, $outcome['operation']->status);
        $this->assertNull($outcome['conflict']);
    }

    /**
     * IDEMPOTENCE CRITIQUE : le rejeu d'une operation deja recue ne doit RIEN
     * re-appliquer. Sans cette garantie, une commande ou un encaissement saisi
     * deux fois hors reseau serait compte deux fois.
     */
    public function test_replaying_the_same_operation_is_idempotent(): void
    {
        $service = app(RestaurantSyncService::class);

        $first = $this->submit('op-replay', RestaurantOfflineOperationType::RecordOrder, ['total_minor' => 1200]);
        $second = $this->submit('op-replay', RestaurantOfflineOperationType::RecordOrder, ['total_minor' => 1200]);

        $this->assertTrue($second['replayed']);
        $this->assertSame($first['operation']->id, $second['operation']->id);
        $this->assertSame(1, DB::table('restaurant_sync_operations')->where('company_id', $this->company->id)->count());
        $this->assertSame(0, $service->statusSummary($this->company)['conflict']);
    }

    /**
     * Un rejeu avec un contenu DIFFERENT n'est pas un rejeu : c'est un conflit
     * d'identifiant, et il est conserve.
     */
    public function test_same_identifier_with_different_payload_is_a_conflict(): void
    {
        $this->submit('op-mismatch', RestaurantOfflineOperationType::RecordOrder, ['total_minor' => 1200]);
        $second = $this->submit('op-mismatch', RestaurantOfflineOperationType::RecordOrder, ['total_minor' => 9999]);

        $this->assertFalse($second['replayed']);
        $this->assertInstanceOf(RestaurantSyncConflict::class, $second['conflict']);
        $this->assertSame(RestaurantSyncStatus::Conflict->value, $second['operation']->status);
    }

    /**
     * CRITERE D'ACCEPTATION CENTRAL : deux terminaux DECONNECTES ne peuvent pas
     * confirmer silencieusement des operations INCOMPATIBLES sur la meme entite.
     *
     * Deux postes deconnectes cloturent la meme table avec des contenus
     * differents : la seconde operation ne doit PAS ecraser la premiere en
     * silence, elle doit partir en conflit.
     */
    public function test_two_disconnected_terminals_cannot_silently_confirm_incompatible_operations(): void
    {
        $first = $this->submit(
            'op-t1',
            RestaurantOfflineOperationType::CloseTableSession,
            ['total_minor' => 5000],
            terminalId: 'terminal-1',
            targetEntityType: 'table_session',
            targetEntityId: 'session-42',
        );

        $this->assertNull($first['conflict']);

        $second = $this->submit(
            'op-t2',
            RestaurantOfflineOperationType::CloseTableSession,
            ['total_minor' => 7300],
            terminalId: 'terminal-2',
            targetEntityType: 'table_session',
            targetEntityId: 'session-42',
        );

        $this->assertInstanceOf(RestaurantSyncConflict::class, $second['conflict']);
        $this->assertSame(RestaurantSyncStatus::Conflict->value, $second['operation']->status);

        // Les DEUX valeurs sont conservees : rien n'a ete ecrase.
        $conflict = $second['conflict'];
        $this->assertSame(5000, $conflict->server_value['total_minor']);
        $this->assertSame(7300, $conflict->local_value['total_minor']);
        $this->assertSame('terminal-1', $conflict->server_terminal_id);
        $this->assertSame('terminal-2', $conflict->local_terminal_id);
    }

    /**
     * Deux terminaux qui confirment EXACTEMENT la meme chose ne sont pas en
     * conflit : c'est une convergence, pas une divergence. Sans cette
     * distinction, chaque double saisie legitime declencherait un faux conflit.
     */
    public function test_two_terminals_confirming_the_same_content_do_not_conflict(): void
    {
        $this->submit(
            'op-same-1',
            RestaurantOfflineOperationType::CloseTableSession,
            ['total_minor' => 5000],
            terminalId: 'terminal-1',
            targetEntityType: 'table_session',
            targetEntityId: 'session-7',
        );

        $second = $this->submit(
            'op-same-2',
            RestaurantOfflineOperationType::CloseTableSession,
            ['total_minor' => 5000],
            terminalId: 'terminal-2',
            targetEntityType: 'table_session',
            targetEntityId: 'session-7',
        );

        $this->assertNull($second['conflict']);
    }

    /**
     * Le MEME terminal peut corriger sa propre sequence : ce n'est pas un
     * conflit inter-terminaux, c'est une correction du meme poste.
     */
    public function test_the_same_terminal_can_correct_its_own_sequence(): void
    {
        $this->submit(
            'op-self-1',
            RestaurantOfflineOperationType::CloseTableSession,
            ['total_minor' => 5000],
            terminalId: 'terminal-1',
            targetEntityType: 'table_session',
            targetEntityId: 'session-9',
        );

        $second = $this->submit(
            'op-self-2',
            RestaurantOfflineOperationType::CloseTableSession,
            ['total_minor' => 5100],
            terminalId: 'terminal-1',
            targetEntityType: 'table_session',
            targetEntityId: 'session-9',
        );

        $this->assertNull($second['conflict']);
    }

    /**
     * AUCUN ECRASEMENT SILENCIEUX : un encaissement divergent part en arbitrage
     * humain, jamais en « dernier ecrit gagne ».
     */
    public function test_a_divergent_payment_requires_human_arbitration(): void
    {
        $this->submit(
            'op-pay-1',
            RestaurantOfflineOperationType::RecordPayment,
            ['amount_minor' => 5000],
            terminalId: 'terminal-1',
            targetEntityType: 'order',
            targetEntityId: 'order-1',
        );

        $second = $this->submit(
            'op-pay-2',
            RestaurantOfflineOperationType::RecordPayment,
            ['amount_minor' => 4500],
            terminalId: 'terminal-2',
            targetEntityType: 'order',
            targetEntityId: 'order-1',
        );

        $this->assertInstanceOf(RestaurantSyncConflict::class, $second['conflict']);
        $this->assertSame(
            RestaurantConflictStrategy::ManualReview->value,
            $second['conflict']->strategy,
        );
    }

    /**
     * Un mouvement de stock divergent exige aussi un arbitrage humain : un stock
     * fausse silencieusement est pire qu'un conflit visible.
     */
    public function test_a_divergent_stock_movement_requires_human_arbitration(): void
    {
        $this->submit(
            'op-stock-1',
            RestaurantOfflineOperationType::RecordStockMovement,
            ['quantity' => 10],
            terminalId: 'terminal-1',
            targetEntityType: 'ingredient',
            targetEntityId: 'ing-1',
        );

        $second = $this->submit(
            'op-stock-2',
            RestaurantOfflineOperationType::RecordStockMovement,
            ['quantity' => 7],
            terminalId: 'terminal-2',
            targetEntityType: 'ingredient',
            targetEntityId: 'ing-1',
        );

        $this->assertInstanceOf(RestaurantSyncConflict::class, $second['conflict']);
        $this->assertSame(
            RestaurantConflictStrategy::ManualReview->value,
            $second['conflict']->strategy,
        );
    }

    /**
     * Une operation ADDITIVE est tranchee localement : elle ne peut pas ecraser
     * une donnee existante, donc exiger un arbitrage serait du bruit.
     */
    public function test_an_additive_operation_is_resolved_locally(): void
    {
        $service = app(RestaurantSyncService::class);

        $this->assertSame(
            RestaurantConflictStrategy::LocalWins,
            $service->strategyFor(RestaurantOfflineOperationType::RecordOrder),
        );
    }

    /**
     * Une operation visant une entite DOIT la nommer : sans entite ciblee, la
     * detection de conflit inter-terminaux est impossible, donc on refuse plutot
     * que d'appliquer a l'aveugle (fail-closed).
     */
    public function test_an_operation_targeting_an_entity_must_name_it(): void
    {
        $this->expectException(RestaurantSyncException::class);

        $this->submit(
            'op-no-target',
            RestaurantOfflineOperationType::CloseTableSession,
            ['total_minor' => 5000],
            terminalId: 'terminal-1',
        );
    }

    /**
     * HISTORIQUE DES ERREURS : une operation hors ligne non prevue est REFUSEE
     * et conservee, jamais appliquee « au mieux ».
     */
    public function test_an_unsupported_operation_is_rejected_and_kept(): void
    {
        $service = app(RestaurantSyncService::class);

        $rejected = $service->rejectUnsupported(
            $this->company,
            'op-unknown',
            'delete_everything',
            ['whatever' => true],
            Carbon::now(),
            'terminal-1',
        );

        $this->assertSame(RestaurantSyncStatus::Rejected->value, $rejected->status);
        $this->assertSame('RESTAURANT_SYNC_UNSUPPORTED_OPERATION', $rejected->last_error);

        $errors = $service->errors($this->company);
        $this->assertCount(1, $errors);
        $this->assertSame('op-unknown', $errors[0]->client_operation_id);
    }

    /**
     * Un conflit non resolu est VISIBLE, et sa resolution est EXPLICITE.
     */
    public function test_conflicts_are_visible_and_resolved_explicitly(): void
    {
        $service = app(RestaurantSyncService::class);

        $this->submit(
            'op-c1',
            RestaurantOfflineOperationType::CloseTableSession,
            ['total_minor' => 5000],
            terminalId: 'terminal-1',
            targetEntityType: 'table_session',
            targetEntityId: 'session-1',
        );

        $second = $this->submit(
            'op-c2',
            RestaurantOfflineOperationType::CloseTableSession,
            ['total_minor' => 6000],
            terminalId: 'terminal-2',
            targetEntityType: 'table_session',
            targetEntityId: 'session-1',
        );

        $conflict = $second['conflict'];
        $this->assertInstanceOf(RestaurantSyncConflict::class, $conflict);

        $unresolved = $service->unresolvedConflicts($this->company);
        $this->assertCount(1, $unresolved);
        $this->assertFalse($unresolved[0]->isResolved());

        $resolved = $service->resolveConflict(
            $this->company,
            $conflict,
            RestaurantConflictStrategy::ServerWins,
            42,
            'Le poste 1 avait le bon montant.',
        );

        $this->assertTrue($resolved->isResolved());
        $this->assertSame(RestaurantConflictStrategy::ServerWins->value, $resolved->resolution);
        $this->assertSame(42, $resolved->resolved_by);
        $this->assertCount(0, $service->unresolvedConflicts($this->company));
    }

    /**
     * Un conflit deja resolu n'est JAMAIS re-resolu : la decision initiale reste
     * auditable.
     */
    public function test_a_resolved_conflict_cannot_be_resolved_again(): void
    {
        $service = app(RestaurantSyncService::class);

        $this->submit(
            'op-r1',
            RestaurantOfflineOperationType::CloseTableSession,
            ['total_minor' => 5000],
            terminalId: 'terminal-1',
            targetEntityType: 'table_session',
            targetEntityId: 'session-2',
        );

        $second = $this->submit(
            'op-r2',
            RestaurantOfflineOperationType::CloseTableSession,
            ['total_minor' => 6000],
            terminalId: 'terminal-2',
            targetEntityType: 'table_session',
            targetEntityId: 'session-2',
        );

        $conflict = $second['conflict'];
        $this->assertInstanceOf(RestaurantSyncConflict::class, $conflict);

        $service->resolveConflict($this->company, $conflict, RestaurantConflictStrategy::ServerWins, 1);

        $this->expectException(RestaurantSyncException::class);
        $service->resolveConflict($this->company, $conflict, RestaurantConflictStrategy::LocalWins, 2);
    }

    /**
     * REPRISE : une operation en echec reste visible et n'est pas rejouee
     * indefiniment — au-dela du plafond elle passe en echec definitif.
     */
    public function test_a_failing_operation_stays_visible_and_stops_being_retried(): void
    {
        $service = app(RestaurantSyncService::class);

        $outcome = $this->submit('op-fail', RestaurantOfflineOperationType::RecordOrder, ['total_minor' => 100]);
        $operation = $outcome['operation'];

        for ($i = 0; $i < RestaurantSyncService::MAX_ATTEMPTS; $i++) {
            $operation = $service->markFailed($this->company, $operation, 'RESTAURANT_SYNC_APPLY_FAILED');
        }

        $this->assertSame(RestaurantSyncStatus::Failed->value, $operation->status);
        $this->assertSame(RestaurantSyncService::MAX_ATTEMPTS, $operation->attempt_count);

        $errors = $service->errors($this->company);
        $this->assertCount(1, $errors);
    }

    /**
     * ISOLATION TENANT : une operation d'une autre entreprise n'est jamais
     * visible ni modifiable.
     */
    public function test_operations_are_isolated_per_company(): void
    {
        $service = app(RestaurantSyncService::class);

        $other = Company::factory()->create();

        $outcome = $this->submit('op-iso', RestaurantOfflineOperationType::RecordOrder, ['total_minor' => 100]);

        $this->assertSame(0, $service->statusSummary($other)['pending']);
        $this->assertCount(0, $service->pending($other));

        $this->expectException(RestaurantSyncException::class);
        $service->markApplied($other, $outcome['operation']);
    }

    /**
     * Le resume d'etat expose exactement ce que le tableau de bord affiche.
     */
    public function test_status_summary_reports_the_queue_state(): void
    {
        $service = app(RestaurantSyncService::class);

        $this->submit('op-s1', RestaurantOfflineOperationType::RecordOrder, ['total_minor' => 100]);
        $this->submit('op-s2', RestaurantOfflineOperationType::RecordOrder, ['total_minor' => 200]);

        $summary = $service->statusSummary($this->company);

        $this->assertSame(2, $summary['pending']);
        $this->assertSame(0, $summary['applied']);
        $this->assertSame(0, $summary['conflict']);
        $this->assertSame(0, $summary['unresolved_conflicts']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{operation: \App\Modules\RestaurantManager\Domain\Models\RestaurantSyncOperation, replayed: bool, conflict: RestaurantSyncConflict|null}
     */
    private function submit(
        string $clientOperationId,
        RestaurantOfflineOperationType $type,
        array $payload,
        ?string $terminalId = null,
        ?string $targetEntityType = null,
        ?string $targetEntityId = null,
    ): array {
        return app(RestaurantSyncService::class)->submit(
            $this->company,
            $clientOperationId,
            $type,
            $payload,
            Carbon::now(),
            $terminalId,
            $targetEntityType,
            $targetEntityId,
        );
    }
}
