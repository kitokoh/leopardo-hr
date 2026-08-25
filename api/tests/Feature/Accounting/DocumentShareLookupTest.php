<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingDocumentShare;
use App\Modules\Accounting\Domain\Models\DocumentShareLookup;
use App\Modules\Accounting\Infrastructure\Services\DocumentShareService;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5428 — résolution du token de partage en O(1) (lookup public).
 *
 * L'ancien comportement itérait TOUTES les entreprises actives (O(N) bascules
 * de search_path par requête publique). Le lookup `document_share_lookup`
 * (public) permet une résolution à nombre de requêtes CONSTANT.
 */
class DocumentShareLookupTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'status' => 'active']);

        return $company;
    }

    private function makeShare(Company $company): AccountingDocumentShare
    {
        $service = app(DocumentShareService::class);

        /** @var \App\Modules\Accounting\Domain\Models\AccountingDocument $document */
        $document = \App\Modules\Accounting\Domain\Models\AccountingDocument::create([
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

        return $service->createShare($document, 'client@exemple.dz');
    }

    public function test_share_creation_writes_public_lookup(): void
    {
        $company = $this->makeCompany();
        app()->instance('current_company', $company);

        $share = $this->makeShare($company);

        $this->assertDatabaseHas('document_share_lookup', [
            'share_token' => $share->share_token,
            'company_id' => $company->id,
        ]);
    }

    public function test_resolution_query_count_is_constant_regardless_of_company_count(): void
    {
        // 5 compagnies actives, chacune avec un partage.
        $companies = [];
        for ($i = 0; $i < 5; $i++) {
            $c = $this->makeCompany();
            app()->instance('current_company', $c);
            $companies[] = $this->makeShare($c);
        }

        // Résolution du partage de la compagnie n°3 (index 2).
        $target = $companies[2]->share_token;

        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $this->getJson('/api/v1/accounting/documents/shared/'.$target)->assertOk();

        // Ancien comportement : ≥ 5 bascules de tenant (une par entreprise),
        // soit bien plus de 10 requêtes. Nouveau : lookup + company + tenant
        // + share + document ≈ 6-8 requêtes, indépendant du nombre d'entreprises.
        $this->assertLessThan(12, $queries, "Résolution en {$queries} requêtes — attendu constant (O(1))");
    }

    public function test_lookup_row_is_removed_by_purge_command(): void
    {
        $company = $this->makeCompany();
        app()->instance('current_company', $company);

        $share = $this->makeShare($company);
        AccountingDocumentShare::query()
            ->where('share_token', $share->share_token)
            ->update(['expires_at' => now()->subDays(40)]);

        $this->artisan('accounting:purge-expired-shares --grace-days=30');

        $this->assertDatabaseMissing('accounting_document_shares', ['share_token' => $share->share_token]);
        $this->assertDatabaseMissing('document_share_lookup', ['share_token' => $share->share_token]);
    }

    public function test_unknown_token_still_404(): void
    {
        $this->getJson('/api/v1/accounting/documents/shared/'.str_repeat('x', 64))->assertStatus(404);
    }
}
