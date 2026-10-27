<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Exceptions\EfaturaConformityException;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use App\Modules\Accounting\Infrastructure\Exports\UblTrInvoiceGenerator;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7938 (V2-5) — e-fatura / e-arşiv (UBL-TR 1.2, GİB) : structure reelle du
 * XML et refus des documents fiscalement inexploitables.
 *
 * Les tests portent sur le XML produit et sur les controles d'identifiant
 * fiscal, pas sur la forme du code : c'est le XML qui est transmis a la GİB
 * par l'entegratör.
 */
class UblTrInvoiceGeneratorTest extends TestCase
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

    public function test_build_produces_ubl_tr_1_2_document(): void
    {
        $document = $this->makeInvoice();

        $xml = (new UblTrInvoiceGenerator)->build($document);

        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml), 'Le XML UBL-TR doit etre bien forme.');

        $root = $dom->documentElement;
        if ($root === null) {
            $this->fail('XML UBL-TR : element racine manquant.');
        }

        $this->assertSame('Invoice', $root->localName);
        $this->assertStringContainsString('Invoice-2', $root->namespaceURI ?? '');

        // En-tete normatif.
        $this->assertStringContainsString('urn:tr:ticarifatura:1.2', $xml, 'CustomizationID du profil');
        $this->assertStringContainsString('TICARIFATURA', $xml, 'ProfileID commercial');
        $this->assertStringContainsString('FTR-2026-0007', $xml, 'Numero de facture');
        $this->assertStringContainsString('2026-10-20', $xml, 'Date d emission ISO');
        $this->assertStringContainsString('SATIS', $xml, 'Type de facture : vente');
        $this->assertStringContainsString('TRY', $xml, 'Devise turque');

        // Parties + identifiants fiscaux.
        $this->assertStringContainsString('Anadolu Yazilim A.S.', $xml, 'Vendeur');
        $this->assertStringContainsString('1234567890', $xml, 'VKN du vendeur');
        $this->assertStringContainsString('Bosphore Ticaret Ltd.', $xml, 'Acheteur');
        $this->assertStringContainsString('9876543210', $xml, 'VKN de l acheteur');

        // Montants + KDV.
        $this->assertStringContainsString('KDV', $xml, 'Scheme KDV (TVA turque)');
        $this->assertStringContainsString('0015', $xml, 'Code GIB de la KDV');
        $this->assertStringContainsString('2000.00', $xml, 'Total HT');
        $this->assertStringContainsString('400.00', $xml, 'Montant de KDV');
        $this->assertStringContainsString('2400.00', $xml, 'Total TTC');
    }

    public function test_credit_note_uses_iade_invoice_type(): void
    {
        $document = $this->makeInvoice(type: 'credit_note');

        $xml = (new UblTrInvoiceGenerator)->build($document);

        $this->assertStringContainsString('>IADE<', $xml, 'Un avoir porte le type IADE');
        $this->assertStringNotContainsString('>SATIS<', $xml);
    }

    public function test_earsiv_profile_allows_buyer_without_tax_id(): void
    {
        // e-arşiv vers un particulier : la vente anonyme est autorisee, donc
        // l'absence de TCKN ne doit PAS bloquer.
        $document = $this->makeInvoice(withBuyerTaxId: false);

        $xml = (new UblTrInvoiceGenerator)->build($document, UblTrInvoiceGenerator::PROFILE_EARSIVFATURA);

        $this->assertStringContainsString('EARSIVFATURA', $xml);
        $this->assertStringContainsString('Bosphore Ticaret Ltd.', $xml);
    }

    public function test_rejects_ticarifatura_without_buyer_tax_id(): void
    {
        // En e-fatura (assujetti a assujetti) le VKN de l'acheteur est
        // obligatoire : sans lui la GİB rejette le document.
        $document = $this->makeInvoice(withBuyerTaxId: false);

        $this->expectException(EfaturaConformityException::class);
        $this->expectExceptionMessageMatches('/identifiant fiscal de l.acheteur absent/');

        (new UblTrInvoiceGenerator)->build($document);
    }

    public function test_rejects_seller_with_invalid_vkn(): void
    {
        // VKN mal forme : la facture serait rejetee a l'import, ou rattachee
        // au mauvais contribuable.
        $document = $this->makeInvoice(sellerVkn: '1234567891');

        $this->expectException(EfaturaConformityException::class);
        $this->expectExceptionMessageMatches('/identifiant fiscal du vendeur .* invalide/');

        (new UblTrInvoiceGenerator)->build($document);
    }

    public function test_rejects_document_without_lines(): void
    {
        $document = $this->makeInvoice(withLines: false);

        $this->expectException(EfaturaConformityException::class);
        $this->expectExceptionMessageMatches('/aucune ligne de facture/');

        (new UblTrInvoiceGenerator)->build($document);
    }

    public function test_rejects_inconsistent_subtotal(): void
    {
        $document = $this->makeInvoice(subtotalHt: 1800.00);

        $this->expectException(EfaturaConformityException::class);
        $this->expectExceptionMessageMatches('/somme des lignes .* differente du sous-total HT/');

        (new UblTrInvoiceGenerator)->build($document);
    }

    public function test_vkn_check_digit_is_enforced(): void
    {
        $generator = new UblTrInvoiceGenerator;

        // Vrais VKN : le chiffre de controle est calcule, pas invente.
        $this->assertTrue($generator->isValidVkn($this->vkn('123456789')), 'VKN valide accepte');
        $this->assertFalse($generator->isValidVkn('1234567890'.'1'), '11 chiffres n est pas un VKN');
        $this->assertFalse($generator->isValidVkn('1234567891'), 'Mauvais chiffre de controle refuse');
    }

    public function test_tckn_check_digits_are_enforced(): void
    {
        $generator = new UblTrInvoiceGenerator;

        $valid = $this->tckn('123456789');
        $this->assertTrue($generator->isValidTckn($valid), 'TCKN valide accepte');

        // Alterer un seul chiffre casse l'un des deux controles.
        $broken = substr($valid, 0, 4).(((int) $valid[4] + 1) % 10).substr($valid, 5);
        $this->assertFalse($generator->isValidTckn($broken), 'TCKN altere refuse');

        $this->assertFalse($generator->isValidTckn('01234567890'), 'Un TCKN ne commence jamais par 0');
    }

    /**
     * Recalcule le chiffre de controle d'un VKN a partir de ses 9 premiers
     * chiffres (algorithme officiel), pour ne pas coder un nombre en dur.
     */
    private function vkn(string $first9): string
    {
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += ((int) $first9[$i] + (9 - $i)) % 10;
        }

        return $first9.((string) ((10 - ($sum % 10)) % 10));
    }

    /**
     * Recalcule les deux chiffres de controle d'un TCKN.
     */
    private function tckn(string $first9): string
    {
        $d = array_map(static fn (string $c): int => (int) $c, str_split($first9));

        $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $even = $d[1] + $d[3] + $d[5] + $d[7];

        $tenth = ((($odd * 7) - $even) % 10 + 10) % 10;
        $eleventh = (array_sum($d) + $tenth) % 10;

        return $first9.(string) $tenth.(string) $eleventh;
    }

    private function makeInvoice(
        ?string $sellerVkn = null,
        bool $withBuyerTaxId = true,
        bool $withLines = true,
        float $subtotalHt = 2000.00,
        string $type = 'invoice',
    ): AccountingDocument {
        $sellerVkn ??= $this->vkn('123456789');
        $buyerTaxId = $withBuyerTaxId ? $this->vkn('987654321') : null;

        $company = Company::factory()->create(['name' => 'Anadolu Yazilim A.S.']);

        $contact = AccountingContact::query()->create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Bosphore Ticaret',
            'legal_name' => 'Bosphore Ticaret Ltd.',
            'tax_id' => $buyerTaxId,
            'currency' => 'TRY',
        ]);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::query()->create([
            'company_id' => $company->id,
            'type' => $type,
            'number' => 'FTR-2026-0007',
            'status' => 'issued',
            'contact_id' => $contact->id,
            'issue_date' => '2026-10-20',
            'due_date' => '2026-11-19',
            'currency' => 'TRY',
            'subtotal_ht' => $subtotalHt,
            'tax_amount' => 400.00,
            'total_ttc' => 2400.00,
            'tva_rate' => 20.0,
            'metadata' => [
                'seller' => [
                    'vkn' => $sellerVkn,
                    'city' => 'Istanbul',
                ],
            ],
        ]);

        if ($withLines) {
            AccountingDocumentLine::query()->create([
                'company_id' => $company->id,
                'document_id' => $document->id,
                'description' => 'Licence logicielle annuelle',
                'quantity' => 2,
                'unit_price' => 1000.00,
                'discount' => 0.00,
                'sort_order' => 1,
            ]);
        }

        return $document->fresh() ?? $document;
    }
}
