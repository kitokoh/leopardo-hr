<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Exceptions\TenantContextMissingException;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\ContactType;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5221 — isolation tenant du module Comptabilité.
 *
 * Le trait BelongsToCompany (garde fail-closed #3727) doit :
 *   - auto-remplir `company_id` à la création ;
 *   - filtrer toutes les requêtes par le tenant courant ;
 *   - lever TenantContextMissingException sur la surface tenant sans
 *     compagnie courante (jamais de fuite cross-tenant).
 */
class AccountingTenantIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;
    }

    protected function tearDown(): void
    {
        // Ne pas laisser le marqueur fail-closed polluer les tests suivants
        // (TenantMiddleware le retire lui-même sur la vraie surface API).
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    public function test_creation_auto_fills_company_id(): void
    {
        app()->instance('current_company', $this->companyA);

        /** @var AccountingContact $contact */
        $contact = AccountingContact::query()->create([
            'type' => ContactType::Customer->value,
            'name' => 'Client A',
        ]);

        $this->assertSame($this->companyA->id, $contact->company_id);
    }

    public function test_models_are_scoped_to_current_tenant(): void
    {
        app()->instance('current_company', $this->companyA);

        /** @var AccountingContact $contact */
        $contact = AccountingContact::query()->create([
            'type' => ContactType::Customer->value,
            'name' => 'Client A',
        ]);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::query()->create([
            'type' => DocumentType::Invoice->value,
            'number' => 'FAC-A-0001',
            'status' => DocumentStatus::Draft->value,
            'contact_id' => $contact->id,
            'issue_date' => '2026-08-22',
            'currency' => 'DZD',
        ]);

        AccountingDocumentLine::query()->create([
            'document_id' => $document->id,
            'description' => 'Ligne A',
            'quantity' => 1,
            'unit_price' => 10.0,
        ]);

        AccountingPayment::query()->create([
            'document_id' => $document->id,
            'amount' => 10.0,
            'method' => 'cash',
            'status' => 'recorded',
        ]);

        AccountingSettings::query()->create([
            'currency' => 'DZD',
            'document_language' => 'fr',
        ]);

        // Bascule vers le tenant B : rien du tenant A ne doit être visible.
        app()->instance('current_company', $this->companyB);

        $this->assertNull(
            AccountingContact::query()->whereKey($contact->id)->first(),
            'Un contact du tenant A ne doit pas être visible depuis le tenant B.'
        );
        $this->assertNull(
            AccountingDocument::query()->whereKey($document->id)->first(),
            'Un document du tenant A ne doit pas être visible depuis le tenant B.'
        );
        $this->assertSame(0, AccountingDocumentLine::query()->count());
        $this->assertSame(0, AccountingPayment::query()->count());
        $this->assertSame(0, AccountingSettings::query()->count());

        // Et le tenant B peut créer ses propres données sans pollution.
        /** @var AccountingContact $contactB */
        $contactB = AccountingContact::query()->create([
            'type' => ContactType::Supplier->value,
            'name' => 'Fournisseur B',
        ]);
        $this->assertSame($this->companyB->id, $contactB->company_id);
    }

    public function test_tenant_scope_required_fails_closed_without_current_company(): void
    {
        app()->instance('current_company', $this->companyA);
        app()->instance('tenant_scope_required', true);

        // Création d'une donnée du tenant A.
        AccountingContact::query()->create([
            'type' => ContactType::Customer->value,
            'name' => 'Client A',
        ]);

        // Sans compagnie courante, la surface tenant refuse toute requête
        // (fail-closed #3727) — pas de fuite cross-tenant silencieuse.
        app()->forgetInstance('current_company');

        $this->expectException(TenantContextMissingException::class);
        AccountingContact::query()->count();
    }
}
