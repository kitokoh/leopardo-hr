<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Enums;

/**
 * Type de document comptable — COMPTABILITE_CONCEPTION.md §4 (table unique,
 * type discriminé).
 *
 * Issue #9367 — les types **achats** ont été ajoutés : le module ne
 * connaissait que des documents de vente, si bien que le compte `401
 * Fournisseurs` (présent dans tous les plans comptables seedés) n'était jamais
 * mouvementé et qu'une facture fournisseur saisie en `invoice` produisait une
 * écriture de vente. Voir `JournalPostingService::POSTABLE_TYPES`.
 *
 * Cycle de vie identique pour tous les types (draft → sent → paid/cancelled,
 * voir `DocumentWorkflowService`) : seul le **sens** des écritures change
 * (voir `JournalPostingService`).
 */
enum DocumentType: string
{
    // ── Documents de vente ─────────────────────────────────────────────────
    case Invoice = 'invoice';
    case Proforma = 'proforma';
    case Quote = 'quote';
    case CreditNote = 'credit_note';
    case DeliveryNote = 'delivery_note';
    case Receipt = 'receipt';

    // ── Documents d'achat (issue #9367) ────────────────────────────────────
    case PurchaseInvoice = 'purchase_invoice';
    case PurchaseCreditNote = 'purchase_credit_note';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }

    /**
     * Types de documents **d'achat** — la contrepartie est le fournisseur
     * (`401`), la charge est au débit (`607`).
     *
     * @return list<string>
     */
    public static function purchaseValues(): array
    {
        return [self::PurchaseInvoice->value, self::PurchaseCreditNote->value];
    }

    /**
     * Types de documents **de vente** — la contrepartie est le client (`411`),
     * le produit est au crédit (`70x`).
     *
     * @return list<string>
     */
    public static function salesValues(): array
    {
        return [
            self::Invoice->value,
            self::Proforma->value,
            self::Quote->value,
            self::CreditNote->value,
            self::DeliveryNote->value,
            self::Receipt->value,
        ];
    }

    public function isPurchase(): bool
    {
        return in_array($this->value, self::purchaseValues(), true);
    }

    public function isSales(): bool
    {
        return in_array($this->value, self::salesValues(), true);
    }

    /**
     * Le type porte-t-il un impact comptable (écriture au journal) ?
     * Proforma, devis et bordereau de livraison n'en portent pas ; le reçu est
     * la preuve d'encaissement (c'est le paiement qui porte la trésorerie).
     */
    public function isPostable(): bool
    {
        return in_array($this->value, [self::Invoice->value, self::CreditNote->value, self::PurchaseInvoice->value, self::PurchaseCreditNote->value], true);
    }
}
