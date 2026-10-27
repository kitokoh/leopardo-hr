<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Exceptions\FacturXConformityException;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use App\Modules\Accounting\Infrastructure\Exports\FacturXGenerator;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7937 — Factur-X (CII / EN16931) : structure reelle et refus des factures
 * fiscalement inexploitables.
 *
 * Les tests portent sur le XML produit et sur les anomalies bloquantes, pas
 * sur la forme du code : c'est le XML qui est soumis a la PDP.
 */
class FacturXGeneratorTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_build_produces_en16931_cii_document(): void
    {
        $document = $this->makeInvoice();

        $xml = (new FacturXGenerator)->build($document);

        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml), 'Le XML CII doit etre bien forme.');

        $root = $dom->documentElement;
        if ($root === null) {
            $this->fail('XML CII : element racine manquant.');
        }

        // Racine CII + espaces de noms normatifs.
        $this->assertSame('CrossIndustryInvoice', $root->localName);
        $this->assertStringContainsString('CrossIndustryInvoice', $root->namespaceURI ?? '');

        $this->assertStringContainsString('urn:cen.eu:en16931:2017', $xml, 'Profil EN16931 declare (BT-24)');
        $this->assertStringContainsString('INV-2026-0042', $xml, 'Numero de facture (BT-1)');
        $this->assertStringContainsString('20261015', $xml, 'Date d emission au format CII 102');
        $this->assertStringContainsString('EUR', $xml, 'Devise (BT-5)');

        // Vendeur / acheteur identifiables.
        $this->assertStringContainsString('Leopardo SARL', $xml, 'Vendeur (BT-27)');
        $this->assertStringContainsString('FR12345678901', $xml, 'TVA vendeur (BT-31)');
        $this->assertStringContainsString('Client ACME', $xml, 'Acheteur (BT-44)');

        // Montants.
        $this->assertStringContainsString('1000.00', $xml, 'Total HT');
        $this->assertStringContainsString('200.00', $xml, 'Montant de TVA');
        $this->assertStringContainsString('1200.00', $xml, 'Total TTC');

        // Type de document : 380 = facture.
        $this->assertStringContainsString('380', $xml, 'TypeCode 380 (BT-22)');
    }

    public function test_credit_note_uses_type_code_381(): void
    {
        $document = $this->makeInvoice(type: 'credit_note');

        $xml = (new FacturXGenerator)->build($document);

        $this->assertStringContainsString('>381<', $xml, 'Un avoir porte le TypeCode 381');
        $this->assertStringNotContainsString('>380<', $xml);
    }

    public function test_rejects_invoice_without_seller_vat(): void
    {
        $document = $this->makeInvoice(sellerVat: null);

        $this->expectException(FacturXConformityException::class);
        $this->expectExceptionMessageMatches('/TVA du vendeur absent \(BT-31\)/');

        (new FacturXGenerator)->build($document);
    }

    public function test_rejects_invoice_without_buyer(): void
    {
        $document = $this->makeInvoice(withContact: false);

        $this->expectException(FacturXConformityException::class);
        $this->expectExceptionMessageMatches('/nom de l.acheteur absent \(BT-44\)/');

        (new FacturXGenerator)->build($document);
    }

    public function test_rejects_invoice_without_lines(): void
    {
        $document = $this->makeInvoice(withLines: false);

        $this->expectException(FacturXConformityException::class);
        $this->expectExceptionMessageMatches('/aucune ligne de facture/');

        (new FacturXGenerator)->build($document);
    }

    public function test_rejects_inconsistent_subtotal(): void
    {
        // Lignes = 1000 HT, sous-total annonce 900 : TVA declaree fausse.
        $document = $this->makeInvoice(subtotalHt: 900.00);

        $this->expectException(FacturXConformityException::class);
        $this->expectExceptionMessageMatches('/somme des lignes .* differente du sous-total HT/');

        (new FacturXGenerator)->build($document);
    }

    public function test_collects_all_anomalies_at_once(): void
    {
        $document = $this->makeInvoice(sellerVat: null, withContact: false);

        try {
            (new FacturXGenerator)->build($document);
            $this->fail('Une facture non conforme doit lever.');
        } catch (FacturXConformityException $e) {
            // Plusieurs anomalies remontees en un passage : une correction
            // unique doit suffire, pas un aller-retour par champ.
            $this->assertGreaterThanOrEqual(2, count($e->anomalies()));
        }
    }

    private function makeInvoice(
        ?string $sellerVat = 'FR12345678901',
        bool $withContact = true,
        bool $withLines = true,
        float $subtotalHt = 1000.00,
        string $type = 'invoice',
    ): AccountingDocument {
        $company = Company::factory()->create(['name' => 'Leopardo SARL']);

        $contact = null;
        if ($withContact) {
            $contact = AccountingContact::query()->create([
                'company_id' => $company->id,
                'type' => 'customer',
                'name' => 'Client ACME',
                'legal_name' => 'Client ACME SAS',
                'tax_id' => 'FR98765432109',
                'currency' => 'EUR',
            ]);
        }

        $metadata = $sellerVat !== null
            ? ['seller' => ['vat_number' => $sellerVat, 'siret' => '12345678900011']]
            : [];

        /** @var AccountingDocument $document */
        $document = AccountingDocument::query()->create([
            'company_id' => $company->id,
            'type' => $type,
            'number' => 'INV-2026-0042',
            'status' => 'issued',
            'contact_id' => $contact?->id,
            'issue_date' => '2026-10-15',
            'due_date' => '2026-11-14',
            'currency' => 'EUR',
            'subtotal_ht' => $subtotalHt,
            'tax_amount' => 200.00,
            'total_ttc' => 1200.00,
            'tva_rate' => 20.0,
            'metadata' => $metadata,
        ]);

        if ($withLines) {
            AccountingDocumentLine::query()->create([
                'company_id' => $company->id,
                'document_id' => $document->id,
                'description' => 'Prestation de conseil',
                'quantity' => 10,
                'unit_price' => 100.00,
                'discount' => 0.00,
                'sort_order' => 1,
            ]);
        }

        return $document->fresh() ?? $document;
    }
}
