<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Exports;

use App\Modules\Accounting\Domain\Exceptions\FacturXConformityException;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use DOMDocument;
use DOMElement;

/**
 * #7937 (V2-4) — Generation Factur-X / Factur-X BASIC, profil EN16931 (CII).
 *
 * Contexte legal : depuis le 09/2026 la **reception** de factures
 * electroniques est obligatoire en France, et l'emission devient progressive.
 * Une facture papier ou un PDF simple n'est plus une facture valable entre
 * assujettis : il faut un flux structure (ici Factur-X, le format franco-
 * allemand base sur le CII de l'UN/CEFACT, profil EN16931).
 *
 * Ce que produit cette classe : le **XML CII** (Cross Industry Invoice). Le
 * PDF/A-3 qui l'encapsule est un conteneur — le XML en est la partie
 * juridiquement normative, et c'est donc par lui que commencent les tests de
 * conformite. L'embarquement PDF/A-3 (Factur-X exige PDF/A-3b) est un travail
 * distinct, dependant d'une bibliotheque PDF/A, et n'est pas simule ici : un
 * PDF qui pretendrait etre PDF/A-3 sans l'etre serait rejete a la reception.
 *
 * Correspondance EN16931 implementee (CII) :
 *   BT-1/BT-2   numero + date d'emission    -> ExchangedDocument/ID, IssueDateTime
 *   BT-5        devise                      -> ExchangedDocumentContext/InvoiceCurrencyCode
 *   BT-9        date d'echeance             -> SpecifiedTradePaymentTerms/DueDateDateTime
 *   BT-22       type de document 380/381     -> ExchangedDocument/TypeCode
 *   BT-24       profil                      -> GuidelineSpecifiedDocumentContextParameter/ID
 *   BT-27/BT-30 vendeur (nom, identifiant)  -> SellerTradeParty
 *   BT-44/BT-48 acheteur                    -> BuyerTradeParty
 *   BT-31/BT-48 numero de TVA               -> SpecifiedTaxRegistration VA
 *   BG-25       lignes (BT-129 quantite, BT-146 PU HT, BT-131 montant net)
 *   BG-23       ventilation TVA par taux (B/S)
 *   BG-22       totaux HT / TVA / TTC
 */
final class FacturXGenerator
{
    /** Profil Factur-X minimum legalement exploitable en France. */
    public const PROFILE_EN16931 = 'urn:cen.eu:en16931:2017';

    /** Profil BASIC (sous-ensemble, sans champs optionnels EN16931). */
    public const PROFILE_BASIC = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0:1.0';

    private const NS_RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';
    private const NS_RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';
    private const NS_QDT = 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100';
    private const NS_UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    /**
     * @return string Le XML CII (Factur-X) de la facture.
     *
     * @throws FacturXConformityException si au moins une anomalie bloquante existe.
     */
    public function build(AccountingDocument $document): string
    {
        $anomalies = $this->validate($document);

        if ($anomalies !== []) {
            throw new FacturXConformityException($anomalies);
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::NS_RSM, 'rsm:CrossIndustryInvoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ram', self::NS_RAM);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:qdt', self::NS_QDT);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:udt', self::NS_UDT);
        $dom->appendChild($root);

        $root->appendChild($this->context($dom));
        $root->appendChild($this->header($dom, $document));
        $root->appendChild($this->tradeParties($dom, $document));
        $root->appendChild($this->settlement($dom, $document));

        return $dom->saveXML() ?: '';
    }

    /**
     * Controles de conformite bloquants.
     *
     * Tous les champs testes ici ont un caractere obligatoire dans EN16931 :
     * les omettre ne degrade pas la facture, cela la rend juridiquement nulle.
     *
     * @return list<string>
     */
    public function validate(AccountingDocument $document): array
    {
        $anomalies = [];
        $ref = $document->number !== ''
            ? 'Facture '.$document->number
            : 'Facture #'.$document->id;

        // BT-1 / BT-2 — numero et date d'emission.
        if ($document->number === '') {
            $anomalies[] = $ref.' : numero de facture absent (BT-1).';
        }

        // BT-5 — devise ISO 4217 sur 3 lettres.
        $currency = (string) $document->currency;
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            $anomalies[] = sprintf('%s : devise invalide « %s » (BT-5 attend un code ISO 4217).', $ref, $currency);
        }

        // BG-25 — au moins une ligne.
        $lines = $this->lines($document);
        if ($lines === []) {
            $anomalies[] = $ref.' : aucune ligne de facture (BG-25 exige au moins une ligne).';
        }

        // BT-27 / BT-30 — vendeur identifiable.
        $seller = $this->seller($document);
        if ($seller['name'] === null) {
            $anomalies[] = $ref.' : nom du vendeur absent (BT-27).';
        }

        if ($seller['vat'] === null) {
            $anomalies[] = $ref.' : numero de TVA du vendeur absent (BT-31) — obligatoire entre assujettis.';
        }

        // BT-44 — acheteur identifiable : sans lui la facture n'a pas de
        // destinataire fiscal, elle est donc inexploitable pour la deduction.
        $buyer = $this->buyer($document);
        if ($buyer['name'] === null) {
            $anomalies[] = $ref.' : nom de l\'acheteur absent (BT-44).';
        }

        // Coherence arithmetique : un total qui ne correspond pas aux lignes
        // est le signe d'un bulletin de facturation mal calcule — le declarer
        // a l'administration engagerait un montant de TVA faux.
        if ($lines !== [] && $document->subtotal_ht > 0.0) {
            $sum = 0.0;
            foreach ($lines as $line) {
                $sum += $this->lineNetAmount($line);
            }

            if (abs($sum - $document->subtotal_ht) > 0.02) {
                $anomalies[] = sprintf(
                    '%s : somme des lignes (%.2f) differente du sous-total HT (%.2f).',
                    $ref,
                    $sum,
                    $document->subtotal_ht,
                );
            }
        }

        return $anomalies;
    }

    private function context(DOMDocument $dom): DOMElement
    {
        $ctx = $dom->createElement('rsm:ExchangedDocumentContext');

        $guideline = $dom->createElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $this->appendText($dom, $guideline, 'ram:ID', self::PROFILE_EN16931);
        $ctx->appendChild($guideline);

        return $ctx;
    }

    private function header(DOMDocument $dom, AccountingDocument $document): DOMElement
    {
        $header = $dom->createElement('rsm:ExchangedDocument');

        $this->appendText($dom, $header, 'ram:ID', (string) $document->number);
        // BT-22 : 380 = facture, 381 = avoir.
        $this->appendText($dom, $header, 'ram:TypeCode', $this->typeCode($document));

        $issue = $dom->createElement('ram:IssueDateTime');
        $this->appendText($dom, $issue, 'udt:DateTimeString', $this->ciiDate($document->issue_date->format('Y-m-d')), ['format' => '102']);
        $header->appendChild($issue);

        return $header;
    }

    private function tradeParties(DOMDocument $dom, AccountingDocument $document): DOMElement
    {
        $parties = $dom->createElement('rsm:SupplyChainTradeTransaction');

        foreach ($this->lines($document) as $index => $line) {
            $parties->appendChild($this->lineItem($dom, $line, $index + 1));
        }

        $parties->appendChild($this->sellerParty($dom, $document));
        $parties->appendChild($this->buyerParty($dom, $document));

        return $parties;
    }

    private function lineItem(DOMDocument $dom, AccountingDocumentLine $line, int $position): DOMElement
    {
        $item = $dom->createElement('ram:IncludedSupplyChainTradeLineItem');
        $this->appendText($dom, $item, 'ram:AssociatedDocumentLineDocument', (string) $position, [], 'ram:LineID');

        $product = $dom->createElement('ram:SpecifiedTradeProduct');
        $this->appendText($dom, $product, 'ram:Name', (string) $line->description);
        $item->appendChild($product);

        $agreement = $dom->createElement('ram:SpecifiedLineTradeAgreement');
        $price = $dom->createElement('ram:NetPriceProductTradePrice');
        $this->appendText($dom, $price, 'ram:ChargeAmount', $this->money((float) $line->unit_price), ['currencyID' => (string) $this->currencyOf($line)]);
        $agreement->appendChild($price);
        $item->appendChild($agreement);

        $delivery = $dom->createElement('ram:SpecifiedLineTradeDelivery');
        $quantity = $dom->createElement('ram:BilledQuantity');
        $quantity->setAttribute('unitCode', 'C62'); // C62 = piece (UN/ECE Rec 20)
        $quantity->appendChild($dom->createTextNode($this->quantity((float) $line->quantity)));
        $delivery->appendChild($quantity);
        $item->appendChild($delivery);

        $settlement = $dom->createElement('ram:SpecifiedLineTradeSettlement');
        $this->appendText($dom, $settlement, 'ram:LineTotalAmount', $this->money($this->lineNetAmount($line)));
        $item->appendChild($settlement);

        return $item;
    }

    private function sellerParty(DOMDocument $dom, AccountingDocument $document): DOMElement
    {
        $seller = $this->seller($document);

        $party = $dom->createElement('ram:SellerTradeParty');
        $this->appendText($dom, $party, 'ram:Name', (string) $seller['name']);

        if ($seller['legal_id'] !== null) {
            $scheme = $dom->createElement('ram:SpecifiedLegalOrganization');
            $id = $dom->createElement('ram:ID');
            // SIREN/SIRET : schemeID 0009 (SIRET) est le code UN/CEFACT pour la France.
            $id->setAttribute('schemeID', '0009');
            $id->appendChild($dom->createTextNode($seller['legal_id']));
            $scheme->appendChild($id);
            $party->appendChild($scheme);
        }

        $tax = $dom->createElement('ram:SpecifiedTaxRegistration');
        $taxId = $dom->createElement('ram:ID');
        $taxId->setAttribute('schemeID', 'VA');
        $taxId->appendChild($dom->createTextNode((string) $seller['vat']));
        $tax->appendChild($taxId);
        $party->appendChild($tax);

        return $party;
    }

    private function buyerParty(DOMDocument $dom, AccountingDocument $document): DOMElement
    {
        $buyer = $this->buyer($document);

        $party = $dom->createElement('ram:BuyerTradeParty');
        $this->appendText($dom, $party, 'ram:Name', (string) $buyer['name']);

        if ($buyer['vat'] !== null) {
            $tax = $dom->createElement('ram:SpecifiedTaxRegistration');
            $taxId = $dom->createElement('ram:ID');
            $taxId->setAttribute('schemeID', 'VA');
            $taxId->appendChild($dom->createTextNode($buyer['vat']));
            $tax->appendChild($taxId);
            $party->appendChild($tax);
        }

        return $party;
    }

    private function settlement(DOMDocument $dom, AccountingDocument $document): DOMElement
    {
        $settlement = $dom->createElement('ram:SpecifiedTradeSettlementHeaderMonetarySummation');

        $this->appendText($dom, $settlement, 'ram:LineTotalAmount', $this->money((float) $document->subtotal_ht));
        $this->appendText($dom, $settlement, 'ram:TaxBasisTotalAmount', $this->money((float) $document->subtotal_ht));
        $this->appendText($dom, $settlement, 'ram:TaxTotalAmount', $this->money((float) $document->tax_amount), ['currencyID' => (string) $document->currency]);
        $this->appendText($dom, $settlement, 'ram:GrandTotalAmount', $this->money((float) $document->total_ttc));
        $this->appendText($dom, $settlement, 'ram:DuePayableAmount', $this->money((float) $document->total_ttc));

        return $settlement;
    }

    /**
     * @return list<AccountingDocumentLine>
     */
    private function lines(AccountingDocument $document): array
    {
        // `lines` est une HasMany : jamais null sur un document charge.
        return array_values($document->lines->all());
    }

    /**
     * Montant net de ligne = quantite x PU - remise.
     */
    private function lineNetAmount(AccountingDocumentLine $line): float
    {
        $gross = (float) $line->quantity * (float) $line->unit_price;
        $discount = (float) $line->discount;

        return round($gross - $discount, 2);
    }

    /**
     * @return array{name: ?string, vat: ?string, legal_id: ?string}
     */
    private function seller(AccountingDocument $document): array
    {
        // Le vendeur est l'entreprise du tenant. `Company` ne porte aujourd'hui
        // ni SIRET ni numero de TVA intracommunautaire : ces identifiants sont
        // donc lus, sans etre inventes, dans les metadonnees du document
        // (renseignees a l'emission). Le jour ou ils migrent vers une colonne
        // dediee, seule cette methode change.
        $metadata = $document->metadata;
        $sellerMeta = is_array($metadata) && isset($metadata['seller']) && is_array($metadata['seller'])
            ? $metadata['seller']
            : [];

        $company = \App\Core\Tenant\Domain\Models\Company::query()->find($document->company_id);
        $name = $company?->name;

        // Un numero de TVA ne peut pas etre devine : soit il est fourni, soit
        // il manque et la facture est non conforme (BT-31 obligatoire).
        $vat = isset($sellerMeta['vat_number']) && is_string($sellerMeta['vat_number'])
            ? trim($sellerMeta['vat_number'])
            : null;

        $legalId = null;
        foreach (['siret', 'siren'] as $key) {
            if (isset($sellerMeta[$key]) && is_string($sellerMeta[$key]) && trim($sellerMeta[$key]) !== '') {
                $legalId = trim($sellerMeta[$key]);
                break;
            }
        }

        return [
            'name' => is_string($name) && $name !== '' ? $name : null,
            'vat' => $vat !== '' ? $vat : null,
            'legal_id' => $legalId,
        ];
    }

    /**
     * @return array{name: ?string, vat: ?string}
     */
    private function buyer(AccountingDocument $document): array
    {
        $contact = $document->contact;

        // Un document sans contact n'a pas de destinataire fiscal : c'est une
        // anomalie bloquante (BT-44), pas une valeur par defaut.
        $name = $contact !== null && $contact->legal_name !== ''
            ? $contact->legal_name
            : $contact?->name;

        return [
            'name' => is_string($name) && $name !== '' ? $name : null,
            // `tax_id` est chiffre au repos (RGPD) : getAttribute le dechiffre.
            'vat' => $this->attribute($contact, 'tax_id'),
        ];
    }

    private function attribute(mixed $model, string $key): ?string
    {
        if (! is_object($model) || ! method_exists($model, 'getAttribute')) {
            return null;
        }

        $value = $model->getAttribute($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function typeCode(AccountingDocument $document): string
    {
        // Un avoir (credit note) doit porter 381 ; sinon 380 (facture).
        return $document->type === 'credit_note' ? '381' : '380';
    }

    private function currencyOf(AccountingDocumentLine $line): string
    {
        $document = $line->document;
        $currency = $document->currency;

        return is_string($currency) && $currency !== '' ? $currency : 'EUR';
    }

    /**
     * Date au format CII 102 (AAAAMMJJ) — le CII n'accepte pas l'ISO en 102.
     */
    public function ciiDate(string $isoDate): string
    {
        return str_replace('-', '', substr($isoDate, 0, 10));
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function quantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function appendText(
        DOMDocument $dom,
        DOMElement $parent,
        string $name,
        string $value,
        array $attributes = [],
        ?string $wrapped = null,
    ): void {
        if ($wrapped !== null) {
            $holder = $dom->createElement($wrapped);
            foreach ($attributes as $k => $v) {
                $holder->setAttribute($k, $v);
            }
            $holder->appendChild($dom->createTextNode($value));
            $parent->appendChild($holder);

            return;
        }

        $el = $dom->createElement($name);
        foreach ($attributes as $k => $v) {
            $el->setAttribute($k, $v);
        }
        $el->appendChild($dom->createTextNode($value));
        $parent->appendChild($el);
    }
}
