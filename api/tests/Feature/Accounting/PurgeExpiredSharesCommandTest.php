<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentShare;
use Illuminate\Support\Carbon;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5430 — purge des partages de documents expirés.
 *
 * Seuls les partages expirés AU-DELÀ du délai de grâce sont supprimés ;
 * `--dry-run` n'a aucun effet ; l'isolation tenant est préservée.
 */
class PurgeExpiredSharesCommandTest extends TestCase
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
            'name' => 'SARL Client Purge',
            'email' => 'client-purge@exemple.dz',
        ]);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $this->company->id,
            'type' => DocumentType::Invoice->value,
            'number' => 'FAC-2026-'.random_int(1000, 9999),
            'status' => DocumentStatus::Sent->value,
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

    private function makeShare(Carbon $expiresAt): AccountingDocumentShare
    {
        /** @var AccountingDocumentShare $share */
        $share = AccountingDocumentShare::create([
            'company_id' => $this->company->id,
            'document_id' => $this->makeDocument()->id,
            'share_token' => 'tok_'.bin2hex(random_bytes(24)),
            'shared_with_email' => 'client@exemple.dz',
            'expires_at' => $expiresAt,
        ]);

        return $share;
    }

    public function test_purges_only_shares_expired_beyond_grace_period(): void
    {
        $expiredBeyondGrace = $this->makeShare(now()->subDays(40));
        $this->makeShare(now()->subDays(5)); // expiré mais dans le délai de grâce
        $this->makeShare(now()->addDays(10)); // non expiré

        $this->artisan('accounting:purge-expired-shares --grace-days=30')
            ->expectsOutputToContain('Total : 1 partage(s) purges.');

        $this->assertDatabaseMissing('accounting_document_shares', ['id' => $expiredBeyondGrace->id]);
        $this->assertSame(2, AccountingDocumentShare::query()->count());
    }

    public function test_dry_run_has_no_effect(): void
    {
        $this->makeShare(now()->subDays(40));

        $this->artisan('accounting:purge-expired-shares --grace-days=30 --dry-run')
            ->expectsOutputToContain('Total : 1 partage(s) a purger (dry-run).');

        $this->assertSame(1, AccountingDocumentShare::query()->count());
    }

    public function test_purge_is_scoped_to_active_companies(): void
    {
        // Entreprise INACTIVE : ses partages expirés ne sont pas purgés.
        /** @var Company $inactive */
        $inactive = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'status' => 'inactive']);

        app()->instance('current_company', $inactive);
        $shareInactive = $this->makeShare(now()->subDays(40));

        app()->instance('current_company', $this->company);
        $this->makeShare(now()->subDays(40));

        $this->artisan('accounting:purge-expired-shares --grace-days=30');

        $this->assertDatabaseHas('accounting_document_shares', ['id' => $shareInactive->id]);
    }
}
