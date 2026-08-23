<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Mail\DocumentShareMail;
use App\Modules\Accounting\Application\Actions\SendDocumentEmail;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentShare;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5225 — Envoi email des documents + portail client sécurisé.
 *
 * PDF en pièce jointe + lien sécurisé (token + expiration) ; endpoints publics
 * info/download limités au document partagé (RGPD) ; token expiré/invalide
 * rejeté ; sent_at + statut `sent` après envoi.
 */
class DocumentShareEmailTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'status' => 'active']);
        $this->company = $company;

        app()->instance('current_company', $company);
    }

    private function makeDocument(): AccountingDocument
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $this->company->id,
            'type' => 'customer',
            'name' => 'SARL Client Test',
            'email' => 'client@exemple.dz',
            'address' => '16 Rue des Oliviers, Alger',
        ]);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $this->company->id,
            'type' => DocumentType::Invoice->value,
            'number' => 'FAC-2026-'.random_int(1000, 9999),
            'status' => DocumentStatus::Draft->value,
            'contact_id' => $contact->id,
            'issue_date' => '2026-08-01',
            'currency' => 'DZD',
            'subtotal_ht' => 1900,
            'tax_amount' => 361,
            'total_ttc' => 2261,
            'tva_rate' => 19,
        ]);

        return $document;
    }

    public function test_send_email_with_attachment_and_secure_link(): void
    {
        Storage::fake('private');
        Mail::fake();

        $document = $this->makeDocument();
        $action = app(SendDocumentEmail::class);

        $token = $action->handle($document, 'client@exemple.dz');

        // Email envoyé au bon destinataire, avec PDF en pièce jointe + lien.
        Mail::assertSent(DocumentShareMail::class, function (DocumentShareMail $mail) use ($document, $token): bool {
            $attachmentNames = collect($mail->attachments())
                ->map(fn ($attachment): ?string => $attachment->as ?? $attachment->name ?? null)
                ->all();

            return $mail->hasTo('client@exemple.dz')
                && in_array($document->type.'-'.$document->number.'.pdf', $attachmentNames, true)
                && str_contains($mail->portalUrl, '/documents/shared/'.$token);
        });

        // Document marqué envoyé + statut sent (workflow #5223).
        $document->refresh();
        $this->assertNotNull($document->sent_at);
        $this->assertSame(DocumentStatus::Sent->value, $document->status);

        // PDF archivé sur le disque privé.
        $this->assertNotNull($document->pdf_path);
        Storage::disk('private')->assertExists($document->pdf_path);
    }

    public function test_public_info_returns_document_metadata(): void
    {
        Storage::fake('private');
        Mail::fake();

        $document = $this->makeDocument();
        $token = app(SendDocumentEmail::class)->handle($document, 'client@exemple.dz');

        $response = $this->getJson('/api/v1/accounting/documents/shared/'.$token);

        $response->assertOk();
        $response->assertJsonPath('data.number', $document->number);
        $response->assertJsonPath('data.type', 'invoice');
        $response->assertJsonPath('data.total_ttc', 2261);
        $response->assertJsonPath('data.status', 'sent');
    }

    public function test_public_download_returns_pdf(): void
    {
        Storage::fake('private');
        Mail::fake();

        $document = $this->makeDocument();
        $token = app(SendDocumentEmail::class)->handle($document, 'client@exemple.dz');

        $response = $this->get('/api/v1/accounting/documents/shared/'.$token.'/download');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->streamedContent());
    }

    public function test_expired_token_is_rejected(): void
    {
        Storage::fake('private');
        Mail::fake();

        $document = $this->makeDocument();
        $token = app(SendDocumentEmail::class)->handle($document, 'client@exemple.dz');

        AccountingDocumentShare::query()
            ->where('share_token', $token)
            ->update(['expires_at' => now()->subDay()]);

        $this->getJson('/api/v1/accounting/documents/shared/'.$token)->assertStatus(404);
        $this->get('/api/v1/accounting/documents/shared/'.$token.'/download')->assertStatus(404);
    }

    public function test_unknown_token_is_rejected(): void
    {
        $this->getJson('/api/v1/accounting/documents/shared/'.str_repeat('x', 64))->assertStatus(404);
        $this->get('/api/v1/accounting/documents/shared/'.str_repeat('x', 64).'/download')->assertStatus(404);
    }

    public function test_share_is_limited_to_its_document(): void
    {
        Storage::fake('private');
        Mail::fake();

        $documentA = $this->makeDocument();
        $documentB = $this->makeDocument();
        $token = app(SendDocumentEmail::class)->handle($documentA, 'client@exemple.dz');

        // Le portail n'expose QUE le document partagé (RGPD).
        $response = $this->getJson('/api/v1/accounting/documents/shared/'.$token);
        $response->assertJsonPath('data.number', $documentA->number);
        $this->assertNotSame($documentB->number, $response->json('data.number'));
    }
}
