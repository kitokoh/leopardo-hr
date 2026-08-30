<?php

declare(strict_types=1);

namespace App\AI\Services;

use App\AI\AIAuditLogger;
use App\AI\Jobs\ExportAiConversationJob;
use App\AI\Models\AiExport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * BC-23-D07 (issue #6239) — exports asynchrones de conversations IA.
 *
 * - `request()`  : crée l'exportation (idempotent par `dedup_key` unique :
 *   une seule exportation par tenant+conversation+format, rejouer la demande
 *   renvoie l'existant ; un échec passé relance le job) puis dispatch le job.
 * - `generate()` : exécution effective (file + statut), idempotente, tracée
 *   dans `ai_audit_logs` (workflow `conversation_export`) et résout la DLQ
 *   associée en cas de succès.
 */
class ConversationExportService
{
    /** @var list<string> */
    public const SUPPORTED_FORMATS = ['json'];

    public function __construct(private readonly AiDeadLetterQueue $deadLetterQueue) {}

    /**
     * @return array<string, mixed>
     */
    public function request(string $companyId, int $userId, int $conversationId, string $format): array
    {
        $conversation = DB::table('ai_conversations')
            ->where('id', $conversationId)
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->first();

        if ($conversation === null) {
            throw (new ModelNotFoundException)->setModel(AiExport::class);
        }

        $dedupKey = AiExport::dedupKey($companyId, $conversationId, $format);
        $existing = AiExport::query()->where('dedup_key', $dedupKey)->first();

        if ($existing !== null) {
            // Idempotence : une exportation en cours ou déjà livrée est
            // renvoyée telle quelle (jamais de doublon ni de re-dispatch).
            if ($existing->status !== AiExport::STATUS_FAILED) {
                return $this->payload($existing);
            }

            // Échec passé → nouvelle tentative (reset pending + dispatch).
            $existing->forceFill([
                'status' => AiExport::STATUS_PENDING,
                'error_message' => null,
            ])->save();

            ExportAiConversationJob::dispatch($existing->id);

            $fresh = $existing->fresh();
            assert($fresh instanceof AiExport);

            return $this->payload($fresh);
        }

        $export = AiExport::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'format' => $format,
            'dedup_key' => $dedupKey,
            'status' => AiExport::STATUS_PENDING,
        ]);

        ExportAiConversationJob::dispatch($export->id);

        Log::info('AI conversation export requested', [
            'ai_export_id' => $export->id,
            'company_id' => $companyId,
            'conversation_id' => $conversationId,
        ]);

        return $this->payload($export);
    }

    /**
     * Exécution effective du job. Idempotente : une exportation déjà `done`
     * n'est pas regénérée.
     */
    public function generate(int $exportId): void
    {
        $export = AiExport::query()->find($exportId);

        if ($export === null) {
            Log::warning("ConversationExportService: ai_exports #{$exportId} introuvable.");

            return;
        }

        if ($export->status === AiExport::STATUS_DONE) {
            return;
        }

        $export->forceFill(['status' => AiExport::STATUS_PROCESSING, 'error_message' => null])->save();

        try {
            $conversation = DB::table('ai_conversations')
                ->where('id', $export->conversation_id)
                ->where('company_id', $export->company_id)
                ->first();

            if ($conversation === null) {
                throw new \RuntimeException("Conversation #{$export->conversation_id} introuvable pour l'export #{$export->id}.");
            }

            /** @var array<int, array{role: string, content: string}> $messages */
            $messages = json_decode((string) ($conversation->messages ?? '[]'), true) ?: [];
            $content = json_encode([
                'exported_at' => now()->toIso8601String(),
                'conversation_id' => (int) $conversation->id,
                'title' => $conversation->title,
                'format_version' => 1,
                'messages' => $messages,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

            $path = sprintf('ai_exports/%s_%s.json', $export->company_id, $export->id);
            Storage::disk('local')->put($path, $content);

            $export->forceFill([
                'status' => AiExport::STATUS_DONE,
                'file_path' => $path,
                'error_message' => null,
            ])->save();

            // Traçabilité : l'exportation est consignée dans l'audit AI
            // (workflow `conversation_export`) — corrélable via conversation_id.
            app(AIAuditLogger::class)->log(
                companyId: $export->company_id,
                userId: $export->user_id,
                conversationId: $export->conversation_id,
                prompt: '[conversation_export]',
                response: "Export de la conversation #{$export->conversation_id} ({$export->format}, export #{$export->id}).",
                toolsCalled: [],
                provider: 'internal',
                model: 'conversation_export',
                inputTokens: 0,
                outputTokens: 0,
                durationMs: 0,
                workflow: 'conversation_export',
            );

            $this->deadLetterQueue->resolveByDedupKey($export->dedup_key);

            Log::info('AI conversation exported', [
                'ai_export_id' => $export->id,
                'company_id' => $export->company_id,
            ]);
        } catch (Throwable $e) {
            $export->forceFill([
                'status' => AiExport::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ])->save();

            report($e);

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AiExport $export): array
    {
        return [
            'id' => $export->id,
            'conversation_id' => $export->conversation_id,
            'format' => $export->format,
            'status' => $export->status,
            'error_message' => $export->error_message,
            'created_at' => optional($export->created_at)->toIso8601String(),
        ];
    }
}
