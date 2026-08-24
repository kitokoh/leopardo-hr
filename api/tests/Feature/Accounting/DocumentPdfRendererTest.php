<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Application\Jobs\GenerateDocumentPdf;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Infrastructure\Services\DocumentPdfRenderer;
use Illuminate\Support\Facades\Storage;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5224 — Génération PDF des documents comptables multi-langues.
 *
 * 6 types × 4 langues rendus sans erreur ; golden amounts (HT/TVA/TTC) ;
 * archivage via job queue idempotent ; RTL pour l'arabe.
 */
class DocumentPdfRendererTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        app()->instance('current_company', $company);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeDocument(DocumentType $type = DocumentType::Invoice, array $overrides = []): AccountingDocument
    {
        $number = 'FAC-2026-'.str_pad((string) $type->value, 2, '0').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $this->company->id,
            'type' => 'customer',
            'name' => 'SARL Client Test',
            'tax_id' => '000016000000000',
            'address' => '16 Rue des Oliviers, Alger',
            'language' => 'fr',
        ]);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create(array_merge([
            'company_id' => $this->company->id,
            'type' => $type->value,
            'number' => $number,
            'status' => DocumentStatus::Sent->value,
            'contact_id' => $contact->id,
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-31',
            'delivery_date' => $type === DocumentType::DeliveryNote ? '2026-08-02' : null,
            'currency' => 'DZD',
            'subtotal_ht' => 1900,
            'tax_amount' => 361,
            'total_ttc' => 2261,
            'tva_rate' => 19,
            'paid_amount' => 0,
            'footer_mentions' => 'Mentions légales par défaut.',
        ], $overrides));

        AccountingDocumentLine::create([
            'company_id' => $this->company->id,
            'document_id' => $document->id,
            'description' => 'Prestation conseil',
            'quantity' => 2,
            'unit_price' => 1000,
            'discount' => 100,
            'sort_order' => 1,
        ]);

        return $document;
    }

    public function test_all_document_types_render_in_all_locales(): void
    {
        $renderer = new DocumentPdfRenderer;

        foreach (DocumentType::cases() as $type) {
            $document = $this->makeDocument($type);

            foreach (['fr', 'en', 'tr', 'ar'] as $locale) {
                $pdf = $renderer->render($document, $locale);

                $this->assertStringStartsWith('%PDF', $pdf, "{$type->value} en {$locale} doit produire un PDF.");
            }
        }
    }

    public function test_golden_amounts_in_view_data(): void
    {
        $renderer = new DocumentPdfRenderer;
        $document = $this->makeDocument();

        $data = $renderer->buildViewData($document, 'fr');

        $this->assertSame(1900.0, $data['totals']['subtotal_ht']);
        $this->assertSame(361.0, $data['totals']['tax_amount']);
        $this->assertSame(19.0, $data['totals']['tva_rate']);
        $this->assertSame(2261.0, $data['totals']['total_ttc']);
        $this->assertSame(0.0, $data['totals']['paid_amount']);
        $this->assertSame(2261.0, $data['totals']['remaining']);

        // Ligne : 2 × 1000 − remise 100 = 1900
        $this->assertCount(1, $data['lines']);
        $this->assertSame(1900.0, $data['lines'][0]['amount']);
        $this->assertSame(2.0, $data['lines'][0]['quantity']);
        $this->assertSame(1000.0, $data['lines'][0]['unit_price']);
        $this->assertSame(100.0, $data['lines'][0]['discount']);
    }

    public function test_remaining_is_total_minus_paid(): void
    {
        $renderer = new DocumentPdfRenderer;
        $document = $this->makeDocument(overrides: ['paid_amount' => 1000]);

        $data = $renderer->buildViewData($document, 'fr');

        $this->assertSame(1000.0, $data['totals']['paid_amount']);
        $this->assertSame(1261.0, $data['totals']['remaining']);
    }

    public function test_rtl_flag_is_true_for_arabic_only(): void
    {
        $renderer = new DocumentPdfRenderer;
        $document = $this->makeDocument();

        $this->assertTrue($renderer->buildViewData($document, 'ar')['rtl']);
        $this->assertFalse($renderer->buildViewData($document, 'fr')['rtl']);
        $this->assertFalse($renderer->buildViewData($document, 'en')['rtl']);
        $this->assertFalse($renderer->buildViewData($document, 'tr')['rtl']);
    }

    public function test_rtl_view_uses_alarai_font_and_shaped_arabic(): void
    {
        $renderer = new DocumentPdfRenderer;
        $document = $this->makeDocument();

        $arHtml = view('pdf.accounting-document', $renderer->buildViewData($document, 'ar'))->render();
        $this->assertStringContainsString('dir="rtl"', $arHtml);
        $this->assertStringContainsString('font-family: Almarai', $arHtml);
        $this->assertSame(
            1,
            preg_match('/[\x{FB50}-\x{FEFF}]/u', $arHtml),
            'Le texte arabe doit être shapingé (formes de présentation) pour dompdf.',
        );

        $frHtml = view('pdf.accounting-document', $renderer->buildViewData($document, 'fr'))->render();
        $this->assertStringContainsString('dir="ltr"', $frHtml);
        $this->assertStringContainsString('font-family: DejaVu Sans', $frHtml);
        $this->assertStringNotContainsString('Almarai', $frHtml);
        $this->assertSame(0, preg_match('/[\x{FB50}-\x{FEFF}]/u', $frHtml), 'Aucun shaping hors arabe.');
    }

    public function test_legal_mentions_priority_settings_then_footer(): void
    {
        $renderer = new DocumentPdfRenderer;
        $document = $this->makeDocument();

        // Sans settings → repli footer_mentions du document.
        $this->assertSame('Mentions légales par défaut.', $renderer->buildViewData($document, 'fr')['legal_mentions']);

        // Avec settings → priorité au paramétrage entreprise.
        AccountingSettings::create([
            'company_id' => $this->company->id,
            'legal_mentions' => 'Mentions légales paramétrées — art. 25 Loi 18-07.',
            'document_language' => 'ar',
        ]);

        $this->assertSame(
            'Mentions légales paramétrées — art. 25 Loi 18-07.',
            $renderer->buildViewData($document, 'fr')['legal_mentions'],
        );
    }

    public function test_generate_job_archives_pdf_idempotently(): void
    {
        Storage::fake('private');

        AccountingSettings::create([
            'company_id' => $this->company->id,
            'document_language' => 'fr',
        ]);

        $document = $this->makeDocument();
        $this->assertNull($document->pdf_path);

        GenerateDocumentPdf::dispatch($document);

        $document->refresh();
        $expectedPath = 'accounting/documents/'.$this->company->id.'/'.$document->id.'.pdf';

        $this->assertSame($expectedPath, $document->pdf_path);
        Storage::disk('private')->assertExists($expectedPath);

        $firstContent = Storage::disk('private')->get($expectedPath);
        $this->assertStringStartsWith('%PDF', (string) $firstContent);

        // Re-dispatch → idempotent (pas de régénération ni d'écrasement).
        $document->update(['notes' => 'note ajoutée après archivage']);
        GenerateDocumentPdf::dispatch($document);

        $this->assertSame($firstContent, Storage::disk('private')->get($expectedPath));
    }
}
