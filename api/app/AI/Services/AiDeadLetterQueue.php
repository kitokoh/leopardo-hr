<?php

declare(strict_types=1);

namespace App\AI\Services;

use App\AI\Jobs\ExportAiConversationJob;
use App\AI\Models\AiDeadLetterEntry;
use App\AI\Models\AiExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * BC-23-D07 (issue #6239) — dead-letter queue dédiée AI.
 *
 * Consigne (une seule fois par `dedup_key`), replay contrôlé
 * (`ai:dlq:replay` : reset pending + re-dispatch du job) et résolution.
 */
class AiDeadLetterQueue
{
    /**
     * Consigne un job IA en échec définitif (retries épuisés).
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(string $companyId, string $jobClass, ?int $jobId, string $dedupKey, string $error, array $payload = []): void
    {
        try {
            AiDeadLetterEntry::query()->updateOrCreate(
                ['dedup_key' => $dedupKey],
                [
                    'company_id' => $companyId,
                    'job_class' => $jobClass,
                    'job_id' => $jobId,
                    'payload' => $payload,
                    'error' => mb_substr($error, 0, 2000),
                    'status' => AiDeadLetterEntry::STATUS_OPEN,
                    'attempts' => DB::raw('attempts + 1'),
                    'resolved_at' => null,
                ],
            );

            Log::error('AI job moved to dead-letter queue', [
                'dedup_key' => $dedupKey,
                'job_class' => $jobClass,
                'company_id' => $companyId,
            ]);
        } catch (Throwable $e) {
            // Non bloquant : le log ci-dessus reste la trace minimale.
            Log::error('AiDeadLetterQueue.record failed', [
                'exception' => $e->getMessage(),
                'dedup_key' => $dedupKey,
            ]);
        }
    }

    /**
     * Rejoue les entrées DLQ `open` (filtres optionnels par tenant / id) :
     * l'exportation repasse `pending` et son job est re-dispatché.
     *
     * @return int nombre d'entrées rejouées
     */
    public function replay(?string $companyId = null, ?int $entryId = null, int $limit = 10): int
    {
        $query = AiDeadLetterEntry::query()->where('status', AiDeadLetterEntry::STATUS_OPEN);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        if ($entryId !== null) {
            $query->where('id', $entryId);
        }

        $entries = $query->orderBy('id')->limit(max(1, min(100, $limit)))->get();
        $replayed = 0;

        foreach ($entries as $entry) {
            try {
                /** @var array<mixed> $payload */
                $payload = $entry->payload;
                /** @var int|null $exportId */
                $exportId = isset($payload['ai_export_id']) ? (int) $payload['ai_export_id'] : null;

                if ($exportId !== null) {
                    $export = AiExport::query()->find($exportId);
                    if ($export !== null) {
                        $export->forceFill([
                            'status' => AiExport::STATUS_PENDING,
                            'error_message' => null,
                        ])->save();
                    }
                }

                $entry->forceFill(['status' => AiDeadLetterEntry::STATUS_REPLAYING])->save();

                if ($exportId !== null) {
                    ExportAiConversationJob::dispatch($exportId);
                }

                $replayed++;
            } catch (Throwable $e) {
                Log::error('AiDlqReplay failed', [
                    'dlq_id' => $entry->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $replayed;
    }

    /**
     * Résout (marque `resolved`) les entrées DLQ d'un job désormais traité
     * avec succès (appelé par le service de génération).
     */
    public function resolveByDedupKey(string $dedupKey): void
    {
        AiDeadLetterEntry::query()
            ->where('dedup_key', $dedupKey)
            ->whereIn('status', [AiDeadLetterEntry::STATUS_OPEN, AiDeadLetterEntry::STATUS_REPLAYING])
            ->update([
                'status' => AiDeadLetterEntry::STATUS_RESOLVED,
                'resolved_at' => now(),
            ]);
    }
}
