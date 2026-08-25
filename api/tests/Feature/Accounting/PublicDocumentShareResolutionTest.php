<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Application\Actions\SendDocumentEmail;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5428 — Résolution du token de partage SANS itération des tenants.
 *
 * Le lookup global direct par `share_token` (O(1), search_path par défaut
 * `shared_tenants,public`) remplace l'itération de toutes les compagnies
 * actives : isolation RGPD conservée (chaque token ne résout QUE son
 * document), temps de réponse indépendant du nombre de tenants (anti-oracle).
 */
class PublicDocumentShareResolutionTest extends TestCase
{
    use RefreshTenantDatabase;

    /** Crée une entreprise active + un document partagé, retourne (company, document, token). */
    private function makeCompanyWithShare(string $suffix): array
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
            'number' => 'FAC-5428-'.$suffix,
            'status' => DocumentStatus::Draft->value,
            'contact_id' => $contact->id,
            'issue_date' => '2026-08-01',
            'currency' => 'DZD',
            'subtotal_ht' => 1900,
            'tax_amount' => 361,
            'total_ttc' => 2261,
            'tva_rate' => 19,
        ]);

        $token = app(SendDocumentEmail::class)->handle($document, 'client-'.$suffix.'@exemple.dz');

        return [$company, $document, $token];
    }

    public function test_tokens_resolve_across_multiple_tenants_without_iteration(): void
    {
        Storage::fake('private');
        Mail::fake();

        [, $documentA, $tokenA] = $this->makeCompanyWithShare('AAA');
        [, $documentB, $tokenB] = $this->makeCompanyWithShare('BBB');

        // Chaque token ne résout QUE son propre document (aucune fuite
        // cross-tenant, même avec le lookup global direct).
        $this->getJson('/api/v1/accounting/documents/shared/'.$tokenA)
            ->assertOk()
            ->assertJsonPath('data.number', $documentA->number);
        $this->getJson('/api/v1/accounting/documents/shared/'.$tokenB)
            ->assertOk()
            ->assertJsonPath('data.number', $documentB->number);
    }

    public function test_invalid_token_returns_404_regardless_of_tenant_count(): void
    {
        Storage::fake('private');
        Mail::fake();

        // Plusieurs tenants actifs : un token invalide doit rester un 404
        // déterministe (aucun oracle de timing lié au nombre d'entreprises).
        foreach (['C1', 'C2', 'C3'] as $suffix) {
            $this->makeCompanyWithShare($suffix);
        }

        $this->getJson('/api/v1/accounting/documents/shared/'.str_repeat('y', 64))->assertStatus(404);
        $this->get('/api/v1/accounting/documents/shared/'.str_repeat('y', 64).'/download')->assertStatus(404);
    }
}
