<?php

declare(strict_types=1);

namespace App\AI\Jobs;

use App\AI\Models\AiDeadLetterEntry;
use App\AI\Models\AiExport;
use App\AI\Services\AiDeadLetterQueue;
use App\AI\Services\ConversationExportService;
use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * BC-23-D07 (issue #6239) — génération asynchrone d'une exportation de
 * conversation IA (file `ai`), idempotente (statut `done` → no-op), tenant
 * scoped (EnsureTenantContext), retries bornés puis dead-letter queue IA.
 */
final class ExportAiConversationJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    private ?string $resolvedCompanyId = null;

    public function __construct(public readonly int $aiExportId)
    {
        $this->onQueue('ai');
    }

    public function tenantCompanyId(): ?string
    {
        if ($this->resolvedCompanyId !== null) {
            return $this->resolvedCompanyId;
        }

        /** @var AiExport|null $export */
        $export = AiExport::query()->withoutGlobalScopes()->find($this->aiExportId);

        return $this->resolvedCompanyId = $export?->company_id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function handle(ConversationExportService $service): void
    {
        $service->generate($this->aiExportId);
    }

    /**
     * Retries épuisés → l'exportation passe `failed` et le job est consigné
     * en dead-letter queue IA (`ai:dlq:replay` pour relancer).
     */
    public function failed(Throwable $e): void
    {
        Log::error('ExportAiConversationJob.failed', [
            'ai_export_id' => $this->aiExportId,
            'exception' => $e->getMessage(),
        ]);

        /** @var AiExport|null $export */
        $export = AiExport::query()->withoutGlobalScopes()->find($this->aiExportId);

        if ($export !== null && in_array($export->status, [AiExport::STATUS_PENDING, AiExport::STATUS_PROCESSING], true)) {
            $export->forceFill([
                'status' => AiExport::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ])->save();
        }

        app(AiDeadLetterQueue::class)->record(
            companyId: (string) ($export?->company_id ?? ''),
            jobClass: self::class,
            jobId: $this->aiExportId,
            dedupKey: $export?->dedup_key ?? 'ai_export:unknown',
            error: $e->getMessage(),
            payload: ['ai_export_id' => $this->aiExportId],
        );
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'ai_export:'.$this->aiExportId,
            'queue:ai',
        ];
    }
}
