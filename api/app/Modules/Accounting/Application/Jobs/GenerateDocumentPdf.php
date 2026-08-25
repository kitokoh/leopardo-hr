<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Accounting\Domain\Contracts\PdfRendererInterface;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Archivage PDF d'un document comptable (issue #5224).
 *
 * Rend le document dans la langue paramétrée (AccountingSettings.document_language,
 * défaut fr), écrit sur le disque privé (RGPD — même politique que les bulletins
 * via ArchivePaySlipsToCabinetJob) et renseigne AccountingDocument.pdf_path.
 * Idempotent : un document déjà archivé n'est pas régénéré.
 *
 * TenantScopedJob : tables tenant (accounting_*) — le middleware EnsureTenantContext
 * établit search_path + current_company avant le run (contrat Queue du monorepo).
 */
final class GenerateDocumentPdf implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const DISK = 'private';

    public function __construct(public readonly AccountingDocument $document) {}

    public function tenantCompanyId(): ?string
    {
        return $this->document->company_id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext()];    }

    public function handle(PdfRendererInterface $renderer): void
    {
        $path = $this->destinationPath();

        if ($this->document->pdf_path !== null && Storage::disk(self::DISK)->exists($this->document->pdf_path)) {
            return; // idempotent : déjà archivé
        }

        try {
            $content = $renderer->render($this->document, $this->resolveLocale());

            Storage::disk(self::DISK)->put($path, $content);

            $this->document->update(['pdf_path' => $path]);
        } catch (Throwable $exception) {
            Log::error('accounting.document_pdf_generation_failed', [
                'document_id' => $this->document->id,
                'company_id' => $this->document->company_id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function resolveLocale(): string
    {
        $settings = AccountingSettings::query()
            ->where('company_id', $this->document->company_id)
            ->first();

        return $settings->document_language ?? 'fr';
    }

    private function destinationPath(): string
    {
        return 'accounting/documents/'.$this->document->company_id.'/'.$this->document->id.'.pdf';
    }
}
