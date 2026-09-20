<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Infrastructure\Services;

use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use App\Modules\EdgeSync\Domain\Models\SyncLog;
use App\Modules\EdgeSync\Domain\Models\SyncQueue;
use App\Modules\EdgeSync\Infrastructure\Jobs\ProcessSyncQueueJob;
use App\Modules\EdgeSync\Infrastructure\Services\EdgeDaemonSyncClient;
use App\Modules\EdgeSync\Interfaces\Api\V1\Controllers\EdgeNodeController;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cloud-side sync engine — applies records an Edge node pushed over HTTP,
 * and resolves conflicts against the Cloud database.
 *
 * IMPORTANT: this service only ever runs on Cloud, invoked by
 * {@see EdgeNodeController::forceSync()}
 * (manual admin-triggered sync) and
 * {@see ProcessSyncQueueJob}
 * (async processing after a real Edge push landed in sync_queue via
 * EdgeNodeController::pushFromEdge()). It must never be invoked from the
 * `edge:sync-daemon` command running on an Edge deployment — that daemon
 * uses {@see EdgeDaemonSyncClient}
 * instead, which performs the actual over-the-wire HTTP push/pull against
 * this Cloud API rather than writing to whatever local database connection
 * happens to be configured.
 *
 * Conflict resolution strategy:
 *   1. Same record modified on both sides → "last write wins" by default.
 *   2. Attendance records created offline → always accepted (additive).
 *   3. Payroll/leave approvals → Cloud wins (authoritative source).
 *   4. Manual override possible via conflict_resolution field.
 */
class SyncEngineService
{
    /**
     * #7840 — registre EXPLICITE des entity_type synchronisables Edge → Cloud,
     * mappés vers leur table tenant. `entity_type` provient du push d'un nœud
     * Edge (validé seulement `required|string|max:100` côté contrôleur) : sans
     * cette allowlist, un nœud compromis pourrait cibler n'importe quelle
     * table du search_path (`public.companies`, `public.user_lookups`, …).
     * Tout type absent de ce registre est rejeté en conflit (jamais appliqué).
     *
     * NB : garder ce registre aligné avec `config/edge.php` (pushable_entities).
     *
     * @var array<string, string> entity_type => table tenant
     */
    private const SYNCABLE_ENTITY_TABLES = [
        'attendance_logs' => 'attendance_logs',
        'absences' => 'absences',
    ];

    /**
     * #7840 — clés qu'un payload poussé par un nœud Edge n'a JAMAIS le droit
     * d'imposer : le `company_id` est toujours forcé depuis le tenant du nœud.
     *
     * @var list<string>
     */
    private const FORBIDDEN_PAYLOAD_KEYS = ['company_id'];

    /**
     * Execute a full bidirectional sync for an Edge node.
     */
    public function sync(EdgeNode $node): SyncLog
    {
        $log = SyncLog::create([
            'edge_node_id' => $node->id,
            'direction' => 'bidirectional',
            'status' => 'running',
            'records_sent' => 0,
            'records_received' => 0,
            'conflicts_detected' => 0,
            'conflicts_resolved' => 0,
            'started_at' => now(),
        ]);

        try {
            DB::transaction(function () use ($node, $log) {
                $pushResult = $this->push($node);
                $pullResult = $this->pull($node);

                $log->update([
                    'status' => 'success',
                    'records_sent' => $pushResult['sent'],
                    'records_received' => $pullResult['received'],
                    'conflicts_detected' => $pushResult['conflicts'] + $pullResult['conflicts'],
                    'conflicts_resolved' => $pushResult['resolved'] + $pullResult['resolved'],
                    'summary' => ['push' => $pushResult, 'pull' => $pullResult],
                    'finished_at' => now(),
                ]);

                $node->update(['last_sync_at' => now()]);
            });
        } catch (\Throwable $e) {
            Log::error('[EdgeSync] Sync failed for node '.$node->id, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }

        return $log->fresh() ?? $log;
    }

    /**
     * Push local Edge data → Cloud.
     *
     * @return array{sent:int, conflicts:int, resolved:int}
     */
    public function push(EdgeNode $node): array
    {
        $pending = SyncQueue::where('edge_node_id', $node->id)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit(config('edge.batch_size', 100))
            ->get();

        $sent = 0;
        $conflicts = 0;
        $resolved = 0;

        foreach ($pending as $item) {
            // #6554 (audit fiabilité M9) : claim CONDITIONNEL — sous sync
            // concurrent, un item déjà réclamé par un autre process (statut
            // passé à processing entre la sélection et l'update) ne doit pas
            // être traité deux fois. L'update atomique `WHERE status='pending'`
            // garantit qu'un seul process gagne la course.
            $claimed = SyncQueue::whereKey($item->getKey())
                ->where('status', 'pending')
                ->update([
                    'status' => 'processing',
                    'attempt_count' => $item->attempt_count + 1,
                ]);

            if ($claimed === 0) {
                continue;
            }

            try {
                $result = $this->applyToCloud($item);

                if ($result['conflict']) {
                    $conflicts++;
                    $resolution = $this->resolveConflict($item, $result);
                    $item->update([
                        'status' => 'conflict',
                        'conflict_resolution' => $resolution,
                        'conflict_note' => $result['conflict_note'] ?? null,
                    ]);
                    $resolved++;
                } else {
                    $item->update(['status' => 'synced', 'synced_at' => now()]);
                    $sent++;
                }
            } catch (\Throwable $e) {
                Log::warning('[EdgeSync] Push failed for queue item '.$item->id, [
                    'error' => $e->getMessage(),
                ]);

                $newStatus = $item->attempt_count >= 5 ? 'failed' : 'pending';
                $item->update(['status' => $newStatus]);
            }
        }

        return compact('sent', 'conflicts', 'resolved');
    }

    /**
     * Pull is a Cloud-side no-op here: the Edge node itself initiates the
     * pull by calling GET /api/v1/edge-node/{id}/pull
     * (EdgeNodeController::pullDelta(), backed by CloudDeltaBuilder). This
     * method exists only so sync() can report a consistent SyncLog shape
     * for the manual admin-triggered sync() path above.
     *
     * @return array{received:int, conflicts:int, resolved:int}
     */
    public function pull(EdgeNode $node): array
    {
        return ['received' => 0, 'conflicts' => 0, 'resolved' => 0];
    }

    /**
     * Apply a queued item to the Cloud database.
     *
     * #7840 : tout entity_type hors du registre {@see self::SYNCABLE_ENTITY_TABLES}
     * est rejeté en conflit (et journalisé) AVANT toute requête — le nom de
     * table n'est jamais dérivé d'une entrée non allowlistée.
     *
     * @return array{conflict:bool, conflict_note:string|null}
     */
    protected function applyToCloud(SyncQueue $item): array
    {
        $table = $this->syncableEntityTables()[$item->entity_type] ?? null;

        if ($table === null) {
            Log::warning('[EdgeSync] entity_type hors allowlist — enregistrement rejeté', [
                'sync_queue_id' => $item->id,
                'edge_node_id' => $item->edge_node_id,
                'entity_type' => $item->entity_type,
                'operation' => $item->operation,
            ]);

            return [
                'conflict' => true,
                'conflict_note' => sprintf(
                    "entity_type '%s' hors allowlist de synchronisation — enregistrement rejeté.",
                    $item->entity_type
                ),
            ];
        }

        return match ($item->entity_type) {
            'attendance_logs' => $this->applyAttendanceLog($item),
            'absences' => $this->applyAbsence($item),
            default => $this->applyGeneric($item, $table),
        };
    }

    /**
     * Registre des entity_type synchronisables (surchargable en test).
     *
     * @return array<string, string> entity_type => table tenant
     */
    protected function syncableEntityTables(): array
    {
        return self::SYNCABLE_ENTITY_TABLES;
    }

    /**
     * #7840 — company_id du tenant propriétaire du nœud Edge ayant poussé
     * l'item. C'est la SEULE source de vérité tenant : le payload ne peut
     * jamais imposer le sien.
     */
    protected function tenantCompanyId(SyncQueue $item): ?string
    {
        /** @var EdgeNode|null $node */
        $node = $item->edgeNode()->withoutGlobalScopes()->first();
        $companyId = $node?->getAttribute('company_id');

        if (is_string($companyId) && $companyId !== '') {
            return $companyId;
        }

        if (is_int($companyId)) {
            return (string) $companyId;
        }

        return null;
    }

    /**
     * #7840 — nettoie un payload poussé par un nœud Edge avant écriture :
     * retire les clés interdites (company_id fourni, id en update ciblé) et
     * force le company_id du contexte tenant.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizePayload(array $payload, string $companyId, string $operation): array
    {
        foreach (self::FORBIDDEN_PAYLOAD_KEYS as $key) {
            unset($payload[$key]);
        }

        if ($operation === 'update') {
            unset($payload['id']);
        }

        $payload['company_id'] = $companyId;

        return $payload;
    }

    /** @return array{conflict: bool, conflict_note: string|null} */
    protected function applyAttendanceLog(SyncQueue $item): array
    {
        // #7840 : le payload ne peut pas imposer son company_id — forçage du
        // tenant du nœud (les nœuds legacy sans tenant restent inchangés).
        $companyId = $this->tenantCompanyId($item);

        // Attendance records are additive — no conflict unless duplicate external_event_id
        $exists = DB::table('attendance_logs')
            ->where('external_event_id', $item->entity_id)
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->exists();

        if ($exists && $item->operation === 'create') {
            return [
                'conflict' => true,
                'conflict_note' => 'Duplicate external_event_id — create skipped.',
            ];
        }

        $payload = $item->payload;
        $payload['synced_from_offline'] = true;

        if ($companyId !== null) {
            $payload = $this->sanitizePayload($payload, $companyId, $item->operation);
        }

        try {
            // #4978 : savepoint — le 23505 attendu (external_event_id dupliqué)
            // est rollbacké localement, pas de 25P02 en aval du job.
            DB::transaction(function () use ($item, $payload, $companyId): void {
                match ($item->operation) {
                    'create' => DB::table('attendance_logs')->insert($payload),
                    'update' => DB::table('attendance_logs')
                        ->where('id', $item->entity_id)
                        ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
                        ->update($payload),
                    'delete' => DB::table('attendance_logs')
                        ->where('id', $item->entity_id)
                        ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
                        ->delete(),
                    default => null,
                };
            });
        } catch (QueryException $e) {
            // Issue #3811 : course entre le exists() ci-dessus et l'insert
            // (index unique attendance_logs.external_event_id) — un sync
            // concurrent a déjà inséré l'événement. 23505 = SQLSTATE
            // unique_violation : résultat conflit idempotent, jamais de 500
            // dans le job de sync (pattern 23505, cf. PartnerService #3238).
            if ($e->getCode() === '23505' && $item->operation === 'create') {
                Log::warning("Sync attendance race on external_event_id {$item->entity_id} — duplicate create skipped.");

                return [
                    'conflict' => true,
                    'conflict_note' => 'Duplicate external_event_id — create skipped.',
                ];
            }

            throw $e;
        }

        return ['conflict' => false, 'conflict_note' => null];
    }

    /** @return array{conflict: bool, conflict_note: string|null} */
    protected function applyAbsence(SyncQueue $item): array
    {
        // #7840 : lecture et écritures scopées au tenant du nœud.
        $companyId = $this->tenantCompanyId($item);

        // Absences: Cloud wins for any approval status changes
        $cloud = DB::table('absences')
            ->where('id', $item->entity_id)
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->first();

        if ($cloud && in_array($cloud->status, ['approved', 'rejected'], true)) {
            return [
                'conflict' => true,
                'conflict_note' => 'Cloud record already approved/rejected — Cloud wins.',
            ];
        }

        $payload = $item->payload;

        if ($companyId !== null) {
            $payload = $this->sanitizePayload($payload, $companyId, $item->operation);
        }

        match ($item->operation) {
            'create' => DB::table('absences')->insert($payload),
            'update' => DB::table('absences')
                ->where('id', $item->entity_id)
                ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
                ->update($payload),
            'delete' => DB::table('absences')
                ->where('id', $item->entity_id)
                ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
                ->delete(),
            default => null,
        };

        return ['conflict' => false, 'conflict_note' => null];
    }

    /**
     * #7840 : la table n'est plus dérivée de `entity_type` — elle provient du
     * registre allowlisté résolu par {@see self::applyToCloud()}, toutes les
     * requêtes sont scopées au tenant du nœud et le payload est nettoyé
     * (company_id forcé, id retiré en update).
     *
     * @return array{conflict: bool, conflict_note: string|null}
     */
    protected function applyGeneric(SyncQueue $item, string $table): array
    {
        $companyId = $this->tenantCompanyId($item);

        if ($companyId === null) {
            Log::warning('[EdgeSync] applyGeneric — nœud Edge sans tenant, enregistrement rejeté', [
                'sync_queue_id' => $item->id,
                'edge_node_id' => $item->edge_node_id,
                'entity_type' => $item->entity_type,
            ]);

            return [
                'conflict' => true,
                'conflict_note' => 'Nœud Edge sans tenant (company_id absent) — enregistrement rejeté.',
            ];
        }

        // Last-write-wins using updated_at timestamp — lecture scopée tenant :
        // un enregistrement d'un autre tenant est invisible ici.
        $cloud = DB::table($table)
            ->where('id', $item->entity_id)
            ->where('company_id', $companyId)
            ->first();
        $localUpdatedAt = Carbon::parse($item->payload['updated_at'] ?? now());

        if ($cloud && Carbon::parse($cloud->updated_at)->gt($localUpdatedAt)) {
            return [
                'conflict' => true,
                'conflict_note' => 'Cloud record is newer — local update ignored.',
            ];
        }

        $payload = $this->sanitizePayload($item->payload, $companyId, $item->operation);

        match ($item->operation) {
            'create' => DB::table($table)->insert($payload),
            'update' => DB::table($table)
                ->where('id', $item->entity_id)
                ->where('company_id', $companyId)
                ->update($payload),
            'delete' => DB::table($table)
                ->where('id', $item->entity_id)
                ->where('company_id', $companyId)
                ->delete(),
            default => null,
        };

        return ['conflict' => false, 'conflict_note' => null];
    }

    /** @param array<string, mixed> $result */
    protected function resolveConflict(SyncQueue $item, array $result): string
    {
        // attendance_logs → local_wins (always accept offline punches when safe)
        if ($item->entity_type === 'attendance_logs') {
            return 'local_wins';
        }

        // absences with approval, generic → cloud_wins
        return 'cloud_wins';
    }
}
