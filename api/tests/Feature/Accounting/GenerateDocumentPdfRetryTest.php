<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Contracts\PdfRendererInterface;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Infrastructure\Jobs\GenerateDocumentPdf;
use RuntimeException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8206 (BOS-017, critère 3) — le comportement de retry de
 * `GenerateDocumentPdf` est explicite : une panne du renderer est
 * RELANCÉE (le job échoue et est reprogrammé selon `$tries = 3` /
 * `$backoff = [10, 60, 300]`), jamais avalée silencieusement.
 */
class GenerateDocumentPdfRetryTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_renderer_failure_is_rethrown_so_the_job_is_retried(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        app()->instance('current_company', $company);

        $renderer = new class implements PdfRendererInterface
        {
            public function render(AccountingDocument $document, string $locale): string
            {
                throw new RuntimeException('renderer PDF indisponible');
            }
        };

        $document = new AccountingDocument;
        $document->id = 99;
        $document->company_id = $company->id;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('renderer PDF indisponible');

        (new GenerateDocumentPdf($document))->handle($renderer);
    }
}
