<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Application\Services\DocumentWorkflowService;
use App\Modules\Accounting\Domain\Exceptions\DocumentWorkflowException;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Domain\Models\AccountingTaxRate;
use App\Modules\Accounting\Infrastructure\Services\TaxRateService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9376 — TVA multi-taux.
 *
 * Le défaut audité : `accounting_documents.tva_rate` est un taux unique au
 * niveau du document, alors que `AccountingSettingsDefaults::tvaRates()`
 * déclare du multi-taux (DZ 19/9, TR 20/10/1, CA TPS+TVQ). Une facture turque
 * mêlant un service à 20 % et un livre à 1 % était donc impossible à saisir.
 *
 * Les montants attendus sont calculés à la main dans chaque test.
 */
class MultiRateVatTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $country = 'TR'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['currency' => 'TRY', 'country' => $country]);

        AccountingSettings::query()->create([
            'company_id' => $company->id,
            'currency' => 'TRY',
            'document_language' => 'fr',
            'number_series' => [],
        ]);

        return $company;
    }

    /**
     * Le référentiel est seedé depuis les taux légaux du pays : TR = 20/10/1.
     */
    public function test_seeding_uses_the_country_legal_rates(): void
    {
        $company = $this->company('TR');

        $created = app(TaxRateService::class)->seedForCompany((string) $company->id, 'TR');

        $this->assertSame(3, $created);

        $rates = AccountingTaxRate::query()
            ->where('company_id', $company->id)
            ->orderByDesc('rate')
            ->pluck('rate')
            ->map(static fn ($rate): float => (float) $rate)
            ->all();

        $this->assertSame([20.0, 10.0, 1.0], $rates);

        // Un seul taux par défaut, le standard.
        $default = app(TaxRateService::class)->defaultRateFor((string) $company->id);
        $this->assertNotNull($default);
        $this->assertSame(20.0, $default->rate);
    }

    /**
     * Le seed est idempotent : re-seeder ne duplique rien et ne réécrit pas un
     * taux que le comptable a modifié.
     */
    public function test_seeding_is_idempotent_and_preserves_edits(): void
    {
        $company = $this->company('TR');
        $service = app(TaxRateService::class);

        $service->seedForCompany((string) $company->id, 'TR');

        $standard = AccountingTaxRate::query()
            ->where('company_id', $company->id)
            ->where('rate', 20.0)
            ->firstOrFail();

        $service->upsert((string) $company->id, [
            'code' => $standard->code,
            'label' => 'KDV %20 (modifié)',
            'rate' => 20.0,
        ]);

        $created = $service->seedForCompany((string) $company->id, 'TR');

        $this->assertSame(0, $created);
        $this->assertSame(3, AccountingTaxRate::query()->where('company_id', $company->id)->count());
        $this->assertSame(
            'KDV %20 (modifié)',
            AccountingTaxRate::query()->where('company_id', $company->id)->where('rate', 20.0)->value('label'),
        );
    }

    /**
     * Critère d'acceptation principal : une facture à deux taux.
     *
     * Calcul manuel :
     *   ligne 1 : service 1 000 × 1 à 20 % → HT 1 000, TVA 200
     *   ligne 2 : livre     500 × 1 à 1 %  → HT   500, TVA   5
     *   totaux : HT 1 500, TVA 205, TTC 1 705
     */
    public function test_invoice_with_two_rates_computes_each_line_tax(): void
    {
        $company = $this->company('TR');
        app(TaxRateService::class)->seedForCompany((string) $company->id, 'TR');

        /** @var AccountingDocument $document */
        $document = app(DocumentWorkflowService::class)->createDraft([
            'type' => 'invoice',
            'issue_date' => '2026-08-10',
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate_code' => 'TVA-STD-20'],
                ['description' => 'Livre', 'quantity' => 1, 'unit_price' => 500, 'tax_rate_code' => 'TVA-SUP-1'],
            ],
        ], (string) $company->id);

        $this->assertSame(1500.0, (float) $document->subtotal_ht);
        $this->assertSame(205.0, (float) $document->tax_amount);
        $this->assertSame(1705.0, (float) $document->total_ttc);

        // Pas de taux unique sur un document multi-taux : on n'invente pas de
        // moyenne, les lignes portent la vérité.
        $this->assertNull($document->tva_rate);

        $lines = AccountingDocumentLine::query()
            ->where('document_id', $document->id)
            ->orderBy('sort_order')
            ->get();

        $first = $lines[0];
        $second = $lines[1];
        $this->assertNotNull($first);
        $this->assertNotNull($second);

        $this->assertSame(20.0, (float) $first->tax_rate);
        $this->assertSame(200.0, (float) $first->tax_amount);
        $this->assertSame(1.0, (float) $second->tax_rate);
        $this->assertSame(5.0, (float) $second->tax_amount);

        // Chaque ligne référence le taux du référentiel qu'elle a appliqué.
        $this->assertNotNull($first->tax_rate_id);
        $this->assertNotNull($second->tax_rate_id);
        $this->assertNotSame($first->tax_rate_id, $second->tax_rate_id);
    }

    /**
     * Non-régression : un document mono-taux (payload `tva_rate` seul) garde
     * exactement le comportement antérieur.
     */
    public function test_single_rate_document_is_unchanged(): void
    {
        $company = $this->company('DZ');

        /** @var AccountingDocument $document */
        $document = app(DocumentWorkflowService::class)->createDraft([
            'type' => 'invoice',
            'issue_date' => '2026-08-10',
            'tva_rate' => 19,
            'lines' => [
                ['description' => 'Prestation', 'quantity' => 2, 'unit_price' => 500],
            ],
        ], (string) $company->id);

        $this->assertSame(1000.0, (float) $document->subtotal_ht);
        $this->assertSame(190.0, (float) $document->tax_amount);
        $this->assertSame(1190.0, (float) $document->total_ttc);
        $this->assertSame(19.0, (float) $document->tva_rate);
    }

    /**
     * Un code de taux inconnu lève : une ligne à 0 % par erreur de saisie est
     * une facture fausse, pas un défaut acceptable.
     */
    public function test_unknown_tax_rate_code_is_rejected(): void
    {
        $company = $this->company('TR');
        app(TaxRateService::class)->seedForCompany((string) $company->id, 'TR');

        $this->expectException(DocumentWorkflowException::class);

        app(DocumentWorkflowService::class)->createDraft([
            'type' => 'invoice',
            'issue_date' => '2026-08-10',
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'tax_rate_code' => 'TVA-INEXISTANT'],
            ],
        ], (string) $company->id);
    }

    /**
     * Un taux utilisé par une ligne ne peut pas être supprimé (l'historique
     * deviendrait muet) ; il peut être désactivé.
     */
    public function test_tax_rate_in_use_cannot_be_deleted(): void
    {
        $company = $this->company('TR');
        $service = app(TaxRateService::class);
        $service->seedForCompany((string) $company->id, 'TR');

        app(DocumentWorkflowService::class)->createDraft([
            'type' => 'invoice',
            'issue_date' => '2026-08-10',
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'tax_rate_code' => 'TVA-STD-20'],
            ],
        ], (string) $company->id);

        $used = AccountingTaxRate::query()
            ->where('company_id', $company->id)
            ->where('code', 'TVA-STD-20')
            ->firstOrFail();

        $this->assertFalse($service->delete((string) $company->id, $used));
        $this->assertTrue(AccountingTaxRate::query()->whereKey($used->id)->exists());

        // Un taux non utilisé se supprime.
        $unused = AccountingTaxRate::query()
            ->where('company_id', $company->id)
            ->where('code', 'TVA-RED-10')
            ->firstOrFail();

        $this->assertTrue($service->delete((string) $company->id, $unused));
    }

    /**
     * Un seul taux par défaut par tenant, même après plusieurs promotions.
     */
    public function test_only_one_default_rate_per_tenant(): void
    {
        $company = $this->company('TR');
        $service = app(TaxRateService::class);
        $service->seedForCompany((string) $company->id, 'TR');

        $service->upsert((string) $company->id, [
            'code' => 'TVA-RED-10',
            'label' => 'KDV indirimli',
            'rate' => 10.0,
            'is_default' => true,
        ]);

        $this->assertSame(1, AccountingTaxRate::query()
            ->where('company_id', $company->id)
            ->where('is_default', true)
            ->count());

        $this->assertSame(10.0, $service->defaultRateFor((string) $company->id)?->rate);
    }
}
