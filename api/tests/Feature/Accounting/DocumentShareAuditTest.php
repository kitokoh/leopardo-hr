<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentShare;
use App\Modules\Accounting\Infrastructure\Services\DocumentShareService;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5429 — audit RGPD des accès au portail client (info/téléchargement).
 */
class DocumentShareAuditTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private AccountingDocumentShare $share;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'status' => 'active']);
        $this->company = $company;
        app()->instance('current_company', $company);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'number' => 'FAC-2026-'.random_int(1000, 9999),
            'status' => 'sent',
            'issue_date' => '2026-08-01',
            'currency' => 'DZD',
            'subtotal_ht' => 1900,
            'tax_amount' => 361,
            'total_ttc' => 2261,
            'tva_rate' => 19,
        ]);

        $this->share = app(DocumentShareService::class)->createShare($document, 'client@exemple.dz');
    }

    public function test_info_access_is_audited(): void
    {
        $this->getJson('/api/v1/accounting/documents/shared/'.$this->share->share_token)->assertOk();

        $this->assertSame(1, DB::table('audit_logs')
            ->where('company_id', $this->company->id)
            ->where('action', 'accounting.share.info')
            ->where('auditable_id', $this->share->id)
            ->count());
    }

    public function test_download_access_is_audited_with_metadata(): void
    {
        \Illuminate\Support\Facades\Storage::fake('private');

        // PDF existant pour le téléchargement
        $document = $this->share->document;
        $pdfPath = 'documents/'.$document->id.'.pdf';
        \Illuminate\Support\Facades\Storage::disk(\App\Modules\Accounting\Application\Jobs\GenerateDocumentPdf::DISK)
            ->put($pdfPath, '%PDF-1.4 test');
        $document->update(['pdf_path' => $pdfPath]);

        $this->get('/api/v1/accounting/documents/shared/'.$this->share->share_token.'/download')->assertOk();

        $log = DB::table('audit_logs')
            ->where('company_id', $this->company->id)
            ->where('action', 'accounting.share.download')
            ->where('auditable_id', $this->share->id)
            ->first();

        $this->assertNotNull($log);
        $metadata = json_decode((string) $log->metadata, true);
        $this->assertSame($this->share->share_token, $metadata['share_token'] ?? null);
    }

    public function test_unknown_token_is_not_audited(): void
    {
        $this->getJson('/api/v1/accounting/documents/shared/'.str_repeat('x', 64))->assertStatus(404);

        $this->assertSame(0, DB::table('audit_logs')->where('action', 'like', 'accounting.share.%')->count());
    }
}
