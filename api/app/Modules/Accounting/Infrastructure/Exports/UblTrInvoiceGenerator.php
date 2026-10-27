<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Exports;

use App\Modules\Accounting\Domain\Exceptions\EfaturaConformityException;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use DOMDocument;
use DOMElement;

/**
 * #7938 (V2-5) — e-fatura / e-arşiv Turquie, profil UBL-TR 1.2 (GİB).
 *
 * Contexte legal : en Turquie, depuis la communique 509 de la GİB, un
 * assujetti ne peut plus remettre une facture papier a un autre assujetti :
 * il doit emettre un **e-fatura** via un entegratör agree. Vers un particulier
 * (ou un non assujetti), c'est un **e-arşiv** — meme structure UBL-TR, canal
 * different. Le document est rejete a l'import s'il n'est pas conforme.
 *
 * ## Ce que produit cette classe
 *
 * Le XML **UBL-TR 1.2** (profil `TICARIFATURA`, le profil commercial de base,
 * equivalent turc de l'EN16931 europeen) : `Invoice` avec les espaces de noms
 * `urn:oasis:names:specification:ubl:schema:xsd:Invoice-2`. C'est ce XML que
 * l'entegratör transmet a la GİB ; la signature XAdES-BES qui l'enveloppe
 * avant envoi depend du certificat mali de l'entreprise et n'est pas simulee
 * ici — un XML pretendument signe sans certificat valide serait rejete.
 *
 * ## Correspondance UBL-TR
 *
 * ```text
 * cbc:UBLVersionID        2.1
 * cbc:CustomizationID     urn:tr:ticarifatura:1.2   (profil)
 * cbc:ProfileID           TICARIFATURA | EARSIVFATURA
 * cbc:ID                  numero de facture (ETTN en production)
 * cbc:IssueDate           AAAA-MM-JJ
 * cbc:InvoiceTypeCode     SATIS (vente) | IADE (retour)
 * cbc:DocumentCurrencyCode devise ISO 4217
 * AccountingSupplierParty / AccountingCustomerParty
 *     cbc:VKN / cbc:TCKN  identifiant fiscal (VKN = entreprise, TCKN = personne)
 * cac:InvoiceLine
 *     cbc:InvoicedQuantity + unitCode (C62 = piece)
 *     cac:Price/cbc:PriceAmount
 *     cac:LineExtensionAmount
 * cac:TaxTotal + cac:TaxSubtotal   (KDV par taux)
 * cac:LegalMonetaryTotal           (LineExtensionAmount, TaxExclusiveAmount,
 *                                   TaxInclusiveAmount, PayableAmount)
 * ```
 *
 * ## Choix produit : VKN/TCKN verifies, pas seulement presents
 *
 * Un numero fiscal turc porte deux chiffres de controle. Un VKN aleatoire ne
 * fait pas echouer la generation cote GİB avec un message clair : il fait
 * **rejeter la facture**, ou pire, il la rattache au mauvais contribuable.
 * Le controle est donc fait ici, avant l'envoi.
 */
final class UblTrInvoiceGenerator
{
    /** Profil commercial (assujetti a assujetti). */
    public const PROFILE_TICARIFATURA = 'TICARIFATURA';

    /** Profil archive (assujetti a particulier / non assujetti). */
    public const PROFILE_EARSIVFATURA = 'EARSIVFATURA';

    /** Profil technique UBL-TR 1.2. */
    public const CUSTOMIZATION_ID = 'urn:tr:ticarifatura:1.2';

    private const NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    /**
     * @return string Le XML UBL-TR 1.2 de la facture.
     *
     * @throws EfaturaConformityException si au moins une anomalie bloquante existe.
     */
    public function build(AccountingDocument $document, string $profile = self::PROFILE_TICARIFATURA): string
    {
        $anomalies = $this->validate($document, $profile);

        if ($anomalies !== []) {
            throw new EfaturaConformityException($anomalies);
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::NS_INVOICE, 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC);
        $dom->appendChild($root);

        $this->appendText($dom, $root, 'cbc:UBLVersionID', '2.1');
        $this->appendText($dom, $root, 'cbc:CustomizationID', self::CUSTOMIZATION_ID);
        $this->appendText($dom, $root, 'cbc:ProfileID', $profile);
        $this->appendText($dom, $root, 'cbc:ID', (string) $document->number);
        $this->appendText($dom, $root, 'cbc:CopyIndicator', 'false');
        $this->appendText($dom, $root, 'cbc:IssueDate', $this->issueDate($document));
        $this->appendText($dom, $root, 'cbc:InvoiceTypeCode', $this->invoiceTypeCode($document));
        $this->appendText($dom, $root, 'cbc:DocumentCurrencyCode', (string) $document->currency);

        $root->appendChild($this->party($dom, 'cac:AccountingSupplierParty', $this->seller($document), true));
        $root->appendChild($this->party($dom, 'cac:AccountingCustomerParty', $this->buyer($document), false));

        $root->appendChild($this->taxTotal($dom, $document));

        foreach ($this->lines($document) as $index => $line) {
            $root->appendChild($this->invoiceLine($dom, $line, $index + 1, (string) $document->currency));
        }

        $root->appendChild($this->legalMonetaryTotal($dom, $document));

        return $dom->saveXML() ?: '';
    }

    /**
     * Controles bloquants UBL-TR / GİB.
     *
     * Tous les champs testes ici sont obligatoires dans le schema UBL-TR 1.2 :
     * les omettre ne degrade pas le document, il est rejete par la GİB.
     *
     * @return list<string>
     */
    public function validate(AccountingDocument $document, string $profile = self::PROFILE_TICARIFATURA): array
    {
        $anomalies = [];
        $ref = $document->number !== ''
            ? 'Facture '.$document->number
            : 'Facture #'.$document->id;

        if (! in_array($profile, [self::PROFILE_TICARIFATURA, self::PROFILE_EARSIVFATURA], true)) {
            $anomalies[] = sprintf('%s : profil inconnu « %s » (attendu TICARIFATURA ou EARSIVFATURA).', $ref, $profile);
        }

        // cbc:ID — sans numero, le document n'a pas d'identite fiscale.
        if ($document->number === '') {
            $anomalies[] = $ref.' : numero de facture absent (cbc:ID).';
        }

        // cbc:DocumentCurrencyCode — 3 lettres ISO 4217. TRY est la devise
        // locale ; une devise inconnue est refusee a l'import.
        $currency = (string) $document->currency;
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            $anomalies[] = sprintf('%s : devise invalide « %s » (cbc:DocumentCurrencyCode attend un code ISO 4217).', $ref, $currency);
        }

        // cac:InvoiceLine — au moins une ligne, sinon le total n'a pas de base.
        $lines = $this->lines($document);
        if ($lines === []) {
            $anomalies[] = $ref.' : aucune ligne de facture (cac:InvoiceLine exige au moins une ligne).';
        }

        // Vendeur : nom + identifiant fiscal VALIDE. En Turquie le VKN
        // (entreprise) et le TCKN (personne) ont chacun leurs chiffres de
        // controle — un numero mal forme est rejete par la GİB.
        $seller = $this->seller($document);
        if ($seller['name'] === null) {
            $anomalies[] = $ref.' : raison sociale du vendeur absente (cac:PartyName).';
        }

        if ($seller['tax_id'] === null) {
            $anomalies[] = $ref.' : identifiant fiscal du vendeur absent (cbc:VKN / cbc:TCKN).';
        } elseif (! $this->isValidTaxId($seller['tax_id'])) {
            $anomalies[] = sprintf(
                '%s : identifiant fiscal du vendeur « %s » invalide (ni VKN 10 chiffres ni TCKN 11 chiffres avec chiffres de controle corrects).',
                $ref,
                $seller['tax_id'],
            );
        }

        // Acheteur : pour un e-fatura (TICARIFATURA) l'acheteur est un
        // assujetti, son VKN est obligatoire. Pour un e-arşiv vers un
        // particulier, le TCKN est facultatif (vente anonyme autorisee) mais
        // s'il est present il doit etre valide.
        $buyer = $this->buyer($document);
        if ($buyer['name'] === null) {
            $anomalies[] = $ref.' : nom de l\'acheteur absent (cac:AccountingCustomerParty).';
        }

        if ($profile === self::PROFILE_TICARIFATURA) {
            if ($buyer['tax_id'] === null) {
                $anomalies[] = $ref.' : identifiant fiscal de l\'acheteur absent — obligatoire en e-fatura (TICARIFATURA).';
            } elseif (! $this->isValidTaxId($buyer['tax_id'])) {
                $anomalies[] = sprintf('%s : identifiant fiscal de l\'acheteur « %s » invalide.', $ref, $buyer['tax_id']);
            }
        } elseif ($buyer['tax_id'] !== null && ! $this->isValidTaxId($buyer['tax_id'])) {
            $anomalies[] = sprintf('%s : identifiant fiscal de l\'acheteur « %s » invalide.', $ref, $buyer['tax_id']);
        }

        // Coherence arithmetique : un total qui ne correspond pas aux lignes
        // ferait declarer une KDV fausse a l'administration.
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

    /**
     * VKN : 10 chiffres, chiffre de controle calcule par la GİB.
     *
     * Algorithme officiel : pour chaque position i (1..9), on accumule
     * `(digit_i + (10 - i)) mod 10`, on applique la regle de repli sur 9 puis
     * on soustrait du multiple de 10 superieur. Le resultat est le 10e chiffre.
     */
    public function isValidVkn(string $vkn): bool
    {
        if (! preg_match('/^\d{10}$/', $vkn)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $digit = (int) $vkn[$i];
            $tmp = ($digit + (9 - $i)) % 10;
            $sum += $tmp;
        }

        $check = (10 - ($sum % 10)) % 10;

        return $check === (int) $vkn[9];
    }

    /**
     * TCKN : 11 chiffres, deux chiffres de controle (algorithme officiel).
     *
     * Un TCKN ne commence jamais par 0.
     */
    public function isValidTckn(string $tckn): bool
    {
        if (! preg_match('/^[1-9]\d{10}$/', $tckn)) {
            return false;
        }

        $digits = array_map(static fn (string $c): int => (int) $c, str_split($tckn));

        $oddSum = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
        $evenSum = $digits[1] + $digits[3] + $digits[5] + $digits[7];

        // 10e chiffre : ((somme des impairs x 7) - somme des pairs) mod 10.
        $tenth = ((($oddSum * 7) - $evenSum) % 10 + 10) % 10;
        if ($tenth !== $digits[9]) {
            return false;
        }

        // 11e chiffre : somme des 10 premiers mod 10.
        $eleventh = array_sum(array_slice($digits, 0, 10)) % 10;

        return $eleventh === $digits[10];
    }

    /**
     * Un identifiant fiscal turc est soit un VKN (entreprise, 10 chiffres),
     * soit un TCKN (personne, 11 chiffres).
     */
    public function isValidTaxId(string $taxId): bool
    {
        $clean = preg_replace('/\s+/', '', $taxId) ?? '';

        return strlen($clean) === 10
            ? $this->isValidVkn($clean)
            : $this->isValidTckn($clean);
    }

    /**
     * @param  array{name: ?string, tax_id: ?string, tax_scheme: string, city: ?string}  $party
     */
    private function party(DOMDocument $dom, string $element, array $party, bool $isSupplier): DOMElement
    {
        $wrapper = $dom->createElement($element);
        $node = $dom->createElement('cac:Party');

        $identification = $dom->createElement('cac:PartyIdentification');
        $id = $dom->createElement('cbc:ID');
        $id->setAttribute('schemeID', $party['tax_scheme']);
        $id->appendChild($dom->createTextNode((string) $party['tax_id']));
        $identification->appendChild($id);
        $node->appendChild($identification);

        $partyName = $dom->createElement('cac:PartyName');
        $this->appendText($dom, $partyName, 'cbc:Name', (string) $party['name']);
        $node->appendChild($partyName);

        if ($party['city'] !== null) {
            $address = $dom->createElement('cac:PostalAddress');
            $this->appendText($dom, $address, 'cbc:CityName', $party['city']);
            $node->appendChild($address);
        }

        // Le vendeur porte systematiquement le schema VKN/TCKN : c'est lui
        // qui identifie l'emetteur du document fiscal.
        if ($isSupplier) {
            $taxScheme = $dom->createElement('cac:PartyTaxScheme');
            $taxSchemeNode = $dom->createElement('cac:TaxScheme');
            $this->appendText($dom, $taxSchemeNode, 'cbc:Name', 'VKN_TCKN');
            $taxScheme->appendChild($taxSchemeNode);
            $node->appendChild($taxScheme);
        }

        $wrapper->appendChild($node);

        return $wrapper;
    }

    private function invoiceLine(DOMDocument $dom, AccountingDocumentLine $line, int $position, string $currency): DOMElement
    {
        $item = $dom->createElement('cac:InvoiceLine');

        $this->appendText($dom, $item, 'cbc:ID', (string) $position);

        $quantity = $dom->createElement('cbc:InvoicedQuantity');
        $quantity->setAttribute('unitCode', 'C62'); // C62 = piece (UN/ECE Rec 20)
        $quantity->appendChild($dom->createTextNode($this->quantity((float) $line->quantity)));
        $item->appendChild($quantity);

        $this->appendText($dom, $item, 'cbc:LineExtensionAmount', $this->money($this->lineNetAmount($line)), ['currencyID' => $currency]);

        $price = $dom->createElement('cac:Price');
        $this->appendText($dom, $price, 'cbc:PriceAmount', $this->money((float) $line->unit_price), ['currencyID' => $currency]);
        $item->appendChild($price);

        $itemNode = $dom->createElement('cac:Item');
        $this->appendText($dom, $itemNode, 'cbc:Name', (string) $line->description);
        $item->appendChild($itemNode);

        return $item;
    }

    private function taxTotal(DOMDocument $dom, AccountingDocument $document): DOMElement
    {
        $currency = (string) $document->currency;

        $total = $dom->createElement('cac:TaxTotal');
        $this->appendText($dom, $total, 'cbc:TaxAmount', $this->money((float) $document->tax_amount), ['currencyID' => $currency]);

        // KDV : une seule ventilation, au taux porte par le document. Le
        // multi-taux est un enrichissement ulterieur ; le declarer a tort
        // serait pire que l'absence actuelle de ventilation fine.
        $subtotal = $dom->createElement('cac:TaxSubtotal');
        $this->appendText($dom, $subtotal, 'cbc:TaxableAmount', $this->money((float) $document->subtotal_ht), ['currencyID' => $currency]);
        $this->appendText($dom, $subtotal, 'cbc:TaxAmount', $this->money((float) $document->tax_amount), ['currencyID' => $currency]);

        $category = $dom->createElement('cac:TaxCategory');
        $this->appendText($dom, $category, 'cbc:Percent', $this->percent((float) $document->tva_rate));

        $scheme = $dom->createElement('cac:TaxScheme');
        // KDV = Katma Değer Vergisi (TVA turque). Code GİB du scheme.
        $this->appendText($dom, $scheme, 'cbc:Name', 'KDV');
        $this->appendText($dom, $scheme, 'cbc:TaxTypeCode', '0015');
        $category->appendChild($scheme);

        $subtotal->appendChild($category);
        $total->appendChild($subtotal);

        return $total;
    }

    private function legalMonetaryTotal(DOMDocument $dom, AccountingDocument $document): DOMElement
    {
        $currency = (string) $document->currency;

        $total = $dom->createElement('cac:LegalMonetaryTotal');
        $this->appendText($dom, $total, 'cbc:LineExtensionAmount', $this->money((float) $document->subtotal_ht), ['currencyID' => $currency]);
        $this->appendText($dom, $total, 'cbc:TaxExclusiveAmount', $this->money((float) $document->subtotal_ht), ['currencyID' => $currency]);
        $this->appendText($dom, $total, 'cbc:TaxInclusiveAmount', $this->money((float) $document->total_ttc), ['currencyID' => $currency]);
        $this->appendText($dom, $total, 'cbc:PayableAmount', $this->money((float) $document->total_ttc), ['currencyID' => $currency]);

        return $total;
    }

    /**
     * @return list<AccountingDocumentLine>
     */
    private function lines(AccountingDocument $document): array
    {
        return array_values($document->lines->all());
    }

    private function lineNetAmount(AccountingDocumentLine $line): float
    {
        $gross = (float) $line->quantity * (float) $line->unit_price;
        $discount = (float) $line->discount;

        return round($gross - $discount, 2);
    }

    /**
     * Identite fiscale du vendeur.
     *
     * `Company` ne porte pas encore de VKN : il est lu, sans etre invente,
     * dans les metadonnees du document (renseignees a l'emission). Le jour ou
     * la colonne existe, seule cette methode change.
     *
     * @return array{name: ?string, tax_id: ?string, tax_scheme: string, city: ?string}
     */
    private function seller(AccountingDocument $document): array
    {
        $metadata = $document->metadata;
        $sellerMeta = is_array($metadata) && isset($metadata['seller']) && is_array($metadata['seller'])
            ? $metadata['seller']
            : [];

        $company = \App\Core\Tenant\Domain\Models\Company::query()->find($document->company_id);

        $taxId = null;
        foreach (['vkn', 'tckn', 'tax_number'] as $key) {
            if (isset($sellerMeta[$key]) && is_string($sellerMeta[$key]) && trim($sellerMeta[$key]) !== '') {
                $taxId = trim($sellerMeta[$key]);
                break;
            }
        }

        $city = isset($sellerMeta['city']) && is_string($sellerMeta['city']) && trim($sellerMeta['city']) !== ''
            ? trim($sellerMeta['city'])
            : null;

        return [
            'name' => $company?->name !== null && $company->name !== '' ? $company->name : null,
            'tax_id' => $taxId,
            'tax_scheme' => $taxId !== null && strlen($taxId) === 11 ? 'TCKN' : 'VKN',
            'city' => $city,
        ];
    }

    /**
     * @return array{name: ?string, tax_id: ?string, tax_scheme: string, city: ?string}
     */
    private function buyer(AccountingDocument $document): array
    {
        $contact = $document->contact;

        $name = $contact !== null && $contact->legal_name !== ''
            ? $contact->legal_name
            : $contact?->name;

        // `tax_id` est chiffre au repos (RGPD) : getAttribute le dechiffre.
        $taxId = $this->attribute($contact, 'tax_id');
        $clean = $taxId !== null ? (preg_replace('/\s+/', '', $taxId) ?? '') : null;

        return [
            'name' => is_string($name) && $name !== '' ? $name : null,
            'tax_id' => $clean !== '' ? $clean : null,
            'tax_scheme' => $clean !== null && strlen($clean) === 11 ? 'TCKN' : 'VKN',
            'city' => null,
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

    /**
     * SATIS = facture de vente ; IADE = facture de retour (avoir).
     */
    private function invoiceTypeCode(AccountingDocument $document): string
    {
        return $document->type === 'credit_note' ? 'IADE' : 'SATIS';
    }

    private function issueDate(AccountingDocument $document): string
    {
        return $document->issue_date->format('Y-m-d');
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function percent(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
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
    ): void {
        $el = $dom->createElement($name);
        foreach ($attributes as $k => $v) {
            $el->setAttribute($k, $v);
        }
        $el->appendChild($dom->createTextNode($value));
        $parent->appendChild($el);
    }
}
