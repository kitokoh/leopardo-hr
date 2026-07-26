<?php

namespace App\Modules\EdgeSync\Application\Services;

use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use App\Modules\EdgeSync\Domain\Models\SyncLog;
use App\Modules\EdgeSync\Domain\Models\SyncQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cloud-side sync engine — applies records an Edge node pushed over HTTP,
 * and resolves conflicts against the Cloud database.
 *
 * IMPORTANT: this service only ever runs on Cloud, invoked by
 * {@see \App\Modules\EdgeSync\Interfaces\Api\V1\EdgeNodeController::sync()}
 * (manual admin-triggered sync) and
 * {@see \App\Modules\EdgeSync\Infrastructure\Jobs\ProcessSyncQueueJob}
 * (async processing after a real Edge push landed in sync_queue via
 * EdgeNodeController::pushFromEdge()). It must never be invoked from the
 * `edge:sync-daemon` command running on an Edge deployment — that daemon
 * uses {@see \App\Modules\EdgeSync\Infrastructure\Services\EdgeDaemonSyncClient}
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
     * Execute a full bidirectional sync for an Edge node.
     */
    public function sync(EdgeNode $node): SyncLog
    {
        $log = SyncLog::create([
            'edge_node_id'       => $node->id,
            'direction'          => 'bidirectional',
            'status'             => 'running',
            'records_sent'       => 0,
            'records_received'   => 0,
            'conflicts_detected' => 0,
            'conflicts_resolved' => 0,
            'started_at'         => now(),
        ]);

        try {
            DB::transaction(function () use ($node, $log) {
                $pushResult = $this->push($node);
                $pullResult = $this->pull($node);

                $log->update([
                    'status'             => 'success',
                    'records_sent'       => $pushResult['sent'],
                    'records_received'   => $pullResult['received'],
                    'conflicts_detected' => $pushResult['conflicts'] + $pullResult['conflicts'],
                    'conflicts_resolved' => $pushResult['resolved'] + $pullResult['resolved'],
                    'summary'            => ['push' => $pushResult, 'pull' => $pullResult],
                    'finished_at'        => now(),
                ]);

                $node->update(['last_sync_at' => now()]);
            });
        } catch (\Throwable $e) {
            Log::error('[EdgeSync] Sync failed for node ' . $node->id, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at'   => now(),
            ]);
        }

        return $log->fresh();
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

        $sent      = 0;
        $conflicts = 0;
        $resolved  = 0;

        foreach ($pending as $item) {
            $item->update([
                'status'        => 'processing',
                'attempt_count' => $item->attempt_count + 1,
            ]);

            try {
                $result = $this->applyToCloud($item);

                if ($result['conflict']) {
                    $conflicts++;
                    $resolution = $this->resolveConflict($item, $result);
                    $item->update([
                        'status'              => 'conflict',
                        'conflict_resolution' => $resolution,
                        'conflict_note'       => $result['conflict_note'] ?? null,
                    ]);
                    $resolved++;
                } else {
                    $item->update(['status' => 'synced', 'synced_at' => now()]);
                    $sent++;
                }
            } catch (\Throwable $e) {
                Log::warning('[EdgeSync] Push failed for queue item ' . $item->id, [
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
     * @return array{conflict:bool, conflict_note:string|null}
     */
    protected function applyToCloud(SyncQueue $item): array
    {
        return match ($item->entity_type) {
            'attendance_logs' => $this->applyAttendanceLog($item),
            'absences'        => $this->applyAbsence($item),
            default           => $this->applyGeneric($item),
        };
    }

    protected function applyAttendanceLog(SyncQueue $item): array
    {
        // Attendance records are additive — no conflict unless duplicate external_event_id
        $exists = DB::table('attendance_logs')
            ->where('external_event_id', $item->entity_id)
            ->exists();

        if ($exists && $item->operation === 'create') {
            return [
                'conflict'      => true,
                'conflict_note' => 'Duplicate external_event_id — create skipped.',
            ];
        }

        $payload                         = $item->payload;
        $payload['synced_from_offline']  = true;

        match ($item->operation) {
            'create' => DB::table('attendance_logs')->insert($payload),
            'update' => DB::table('attendance_logs')
                ->where('id', $item->entity_id)
                ->update($payload),
            'delete' => DB::table('attendance_logs')
                ->where('id', $item->entity_id)
                ->delete(),
            default  => null,
        };

        return ['conflict' => false, 'conflict_note' => null];
    }

    protected function applyAbsence(SyncQueue $item): array
    {
        // Absences: Cloud wins for any approval status changes
        $cloud = DB::table('absences')->where('id', $item->entity_id)->first();

        if ($cloud && in_array($cloud->status, ['approved', 'rejected'], true)) {
            return [
                'conflict'      => true,
                'conflict_note' => 'Cloud record already approved/rejected — Cloud wins.',
            ];
        }

        $payload = $item->payload;

        match ($item->operation) {
            'create' => DB::table('absences')->insert($payload),
            'update' => DB::table('absences')->where('id', $item->entity_id)->update($payload),
            'delete' => DB::table('absences')->where('id', $item->entity_id)->delete(),
            default  => null,
        };

        return ['conflict' => false, 'conflict_note' => null];
    }

    protected function applyGeneric(SyncQueue $item): array
    {
        // Last-write-wins using updated_at timestamp
        $cloud          = DB::table($item->entity_type)->where('id', $item->entity_id)->first();
        $localUpdatedAt = Carbon::parse($item->payload['updated_at'] ?? now());

        if ($cloud && Carbon::parse($cloud->updated_at)->gt($localUpdatedAt)) {
            return [
                'conflict'      => true,
                'conflict_note' => 'Cloud record is newer — local update ignored.',
            ];
        }

        match ($item->operation) {
            'create' => DB::table($item->entity_type)->insert($item->payload),
            'update' => DB::table($item->entity_type)
                ->where('id', $item->entity_id)
                ->update($item->payload),
            'delete' => DB::table($item->entity_type)
                ->where('id', $item->entity_id)
                ->delete(),
            default  => null,
        };

        return ['conflict' => false, 'conflict_note' => null];
    }

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
