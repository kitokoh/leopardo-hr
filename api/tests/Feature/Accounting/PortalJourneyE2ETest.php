<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Application\Actions\SendDocumentEmail;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentShare;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5433 — E2E portail client : parcours complet de bout en bout
 * (émetteur → partage → email → lien → consultation → téléchargement →
 * expiration → 404), avec le code RÉEL (pattern CriticalFunnelPayrollE2ETest
 * #5285).
 */
class PortalJourneyE2ETest extends TestCase
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

    public function test_full_portal_journey(): void
    {
        Storage::fake('private');
        Mail::fake();

        // 1) Document + contact réels.
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $this->company->id,
            'type' => 'customer',
            'name' => 'SARL Client E2E',
            'email' => 'client-e2e@exemple.dz',
        ]);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'number' => 'FAC-2026-'.random_int(1000, 9999),
            'status' => 'draft',
            'contact_id' => $contact->id,
            'issue_date' => '2026-08-01',
            'currency' => 'DZD',
            'subtotal_ht' => 1900,
            'tax_amount' => 361,
            'total_ttc' => 2261,
            'tva_rate' => 19,
        ]);

        // 2) Envoi : génère le PDF, crée le partage, envoie l'email avec le lien.
        $token = app(SendDocumentEmail::class)->handle($document, 'client-e2e@exemple.dz');
        $this->assertNotEmpty($token);

        Mail::assertSent(\App\Mail\DocumentShareMail::class);

        // 3) Le client ouvre le lien : méta complète.
        $this->getJson('/api/v1/accounting/documents/shared/'.$token)
            ->assertOk()
            ->assertJsonPath('data.number', $document->number)
            ->assertJsonPath('data.status', 'sent');

        // 4) Téléchargement du PDF réel.
        $this->get('/api/v1/accounting/documents/shared/'.$token.'/download')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // 5) Expiration → 404 (le lien devient mort).
        AccountingDocumentShare::query()
            ->where('share_token', $token)
            ->update(['expires_at' => now()->subDay()]);

        $this->getJson('/api/v1/accounting/documents/shared/'.$token)->assertStatus(404);
        $this->get('/api/v1/accounting/documents/shared/'.$token.'/download')->assertStatus(404);

        // 6) Token inconnu → 404 (aucune fuite).
        $this->getJson('/api/v1/accounting/documents/shared/'.str_repeat('x', 64))->assertStatus(404);
    }
}
