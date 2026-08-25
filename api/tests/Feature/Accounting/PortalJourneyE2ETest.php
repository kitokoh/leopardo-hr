<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Tenant\Domain\Models\Company;
use App\Mail\DocumentShareMail;
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

        Mail::assertSent(DocumentShareMail::class);

        // 3) Le client ouvre le lien : méta complète.
        $this->getJson('/api/v1/accounting/documents/shared/'.$token)
            ->assertOk()
            ->assertJsonPath('data.number', $document->number)
            ->assertJsonPath('data.status', 'sent');

        // 4) Téléchargement du PDF réel.
        $this->get('/api/v1/accounting/documents/shared/'.$token.'/download')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // 4bis) RGPD — les accès info/download sont tracés (issue #5429).
        $this->assertSame(1, AuditLog::query()->where('action', 'accounting.share.info')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'accounting.share.download')->count());

        // 4ter) RGPD — les métadonnées exposées sont limitées (pas de données sensibles).
        $this->getJson('/api/v1/accounting/documents/shared/'.$token)
            ->assertOk()
            ->assertJsonMissingPath('data.contact_id')
            ->assertJsonMissingPath('data.company_id')
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.contact');

        // 5) Expiration → 404 (le lien devient mort).
        AccountingDocumentShare::query()
            ->where('share_token', $token)
            ->update(['expires_at' => now()->subDay()]);

        $this->getJson('/api/v1/accounting/documents/shared/'.$token)->assertStatus(404);
        $this->get('/api/v1/accounting/documents/shared/'.$token.'/download')->assertStatus(404);

        // 6) Token inconnu → 404 (aucune fuite).
        $this->getJson('/api/v1/accounting/documents/shared/'.str_repeat('x', 64))->assertStatus(404);

        // 7) RGPD cross-tenant : un token ne mène JAMAIS aux documents d'un
        // autre tenant (résolution par token unique + isExpired — fail-closed).
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'status' => 'active']);
        /** @var AccountingContact $otherContact */
        $otherContact = AccountingContact::create([
            'company_id' => $otherCompany->id,
            'type' => 'customer',
            'name' => 'Autre Tenant',
            'email' => 'autre@exemple.dz',
        ]);
        /** @var AccountingDocument $otherDocument */
        $otherDocument = AccountingDocument::create([
            'company_id' => $otherCompany->id,
            'type' => 'invoice',
            'number' => 'FAC-2026-'.random_int(1000, 9999),
            'status' => 'draft',
            'contact_id' => $otherContact->id,
            'issue_date' => '2026-08-01',
            'currency' => 'DZD',
            'subtotal_ht' => 500,
            'tax_amount' => 95,
            'total_ttc' => 595,
            'tva_rate' => 19,
        ]);
        $otherToken = app(SendDocumentEmail::class)->handle($otherDocument, 'autre@exemple.dz');

        // Depuis le contexte du tenant A, le token du tenant B n'existe pas.
        app()->instance('current_company', $this->company);
        $this->getJson('/api/v1/accounting/documents/shared/'.$otherToken)->assertStatus(404);
    }
}
