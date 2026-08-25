<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentShare;
use Illuminate\Support\Facades\Artisan;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5430 — Purge périodique des partages de documents expirés.
 *
 * `accounting:purge-expired-shares` : suppression des partages expirés au-delà
 * du délai de grâce (RGPD), `--dry-run` sans effet, compteurs par tenant,
 * isolation conservée (seules les lignes expirées + grâce dépassée partent).
 */
class PurgeExpiredSharesTest extends TestCase
{
    use RefreshTenantDatabase;

    /** Crée une entreprise active avec un document et retourne (company, document). */
    private function makeCompanyWithDocument(string $suffix): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'status' => 'active']);
        app()->instance('current_company', $company);

        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client '.$suffix,
            'email' => 'client-'.$suffix.'@exemple.dz',
        ]);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => DocumentType::Invoice->value,
            'number' => 'FAC-5430-'.$suffix,
            'status' => DocumentStatus::Draft->value,
            'contact_id' => $contact->id,
            'issue_date' => '2026-08-01',
            'currency' => 'DZD',
            'subtotal_ht' => 1900,
            'tax_amount' => 361,
            'total_ttc' => 2261,
            'tva_rate' => 19,
        ]);

        return [$company, $document];
    }

    private function makeShare(Company $company, int $documentId, int $daysFromNow): AccountingDocumentShare
    {
        /** @var AccountingDocumentShare $share */
        $share = AccountingDocumentShare::create([
            'company_id' => $company->id,
            'document_id' => $documentId,
            'share_token' => 'tok-'.bin2hex(random_bytes(16)),
            'shared_with_email' => 'client@exemple.dz',
            'expires_at' => now()->addDays($daysFromNow),
        ]);

        return $share;
    }

    public function test_purge_deletes_only_expired_past_grace(): void
    {
        [$company, $document] = $this->makeCompanyWithDocument('AAA');

        $expiredLongAgo = $this->makeShare($company, $document->id, -40); // expiré il y a 40 j (grâce 30 dépassée) → supprimé
        $expiredRecent = $this->makeShare($company, $document->id, -10);  // expiré il y a 10 j (grâce 30 non atteinte) → conservé
        $notExpired = $this->makeShare($company, $document->id, 5);       // non expiré → conservé

        $exit = Artisan::call('accounting:purge-expired-shares', ['--grace-days' => 30]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseMissing('accounting_document_shares', ['id' => $expiredLongAgo->id]);
        $this->assertDatabaseHas('accounting_document_shares', ['id' => $expiredRecent->id]);
        $this->assertDatabaseHas('accounting_document_shares', ['id' => $notExpired->id]);
    }

    public function test_dry_run_does_not_delete(): void
    {
        [$company, $document] = $this->makeCompanyWithDocument('BBB');

        $expired = $this->makeShare($company, $document->id, -40);

        Artisan::call('accounting:purge-expired-shares', ['--grace-days' => 30, '--dry-run' => true]);

        $this->assertDatabaseHas('accounting_document_shares', ['id' => $expired->id]);
    }

    public function test_purge_covers_multiple_tenants(): void
    {
        [$companyA, $documentA] = $this->makeCompanyWithDocument('C1');
        [$companyB, $documentB] = $this->makeCompanyWithDocument('C2');

        $shareA = $this->makeShare($companyA, $documentA->id, -40);
        $shareB = $this->makeShare($companyB, $documentB->id, -40);

        Artisan::call('accounting:purge-expired-shares', ['--grace-days' => 30]);

        $this->assertDatabaseMissing('accounting_document_shares', ['id' => $shareA->id]);
        $this->assertDatabaseMissing('accounting_document_shares', ['id' => $shareB->id]);
    }
}
