<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Printing;

use App\Modules\RestaurantManager\Domain\Contracts\CustomerReceiptRendererInterface;
use App\Modules\RestaurantManager\Domain\ValueObjects\CustomerReceipt;

/**
 * Rend un REÇU CLIENT en flux ESC/POS pour imprimante thermique 58/80 mm
 * (REST-OS-8, #8610).
 *
 * Classe PURE : aucune I/O, aucun accès Eloquent — testable sans base ni
 * matériel.
 *
 * Décisions de conception :
 *
 * 1. **Aucun paiement non validé n'est confirmé.** Si le reçu n'est pas
 *    intégralement réglé, le pied affiche « NON ACQUITTÉ » et le montant
 *    restant dû — jamais un remerciement de fin de vente. L'issue l'exige
 *    explicitement : un reçu qui remercie le client pour un paiement en
 *    attente est un document trompeur.
 *
 * 2. **Aucune arithmétique monétaire ici.** Les montants arrivent en minor
 *    units entières, déjà calculés côté serveur ; le renderer ne fait que
 *    formater. Une division ou un arrondi dans la couche d'impression serait
 *    une seconde source de vérité pour un chiffre comptable.
 *
 * 3. **Les mentions légales sont imprimées telles quelles.** Le renderer ne
 *    connaît aucune règle fiscale (voir ReceiptMentionsResolver) : une
 *    évolution réglementaire ne doit pas toucher au format d'impression.
 */
final class EscPosCustomerReceiptRenderer implements CustomerReceiptRendererInterface
{
    private const ESC = "\x1B";

    private const GS = "\x1D";

    private const LF = "\n";

    private const CHARS_PER_LINE_58 = 32;

    private const CHARS_PER_LINE_80 = 48;

    public function render(CustomerReceipt $receipt, int $paperWidth = 80): string
    {
        $width = $paperWidth <= 58 ? self::CHARS_PER_LINE_58 : self::CHARS_PER_LINE_80;

        return $this->init()
            .$this->header($receipt, $width)
            .$this->body($receipt, $width)
            .$this->totals($receipt, $width)
            .$this->footer($receipt, $width);
    }

    private function init(): string
    {
        // Reset, encodage PC437 (accents FR), alignement gauche.
        return self::ESC.'@'.self::ESC.'t'.chr(0).self::ESC.'a'.chr(0);
    }

    private function header(CustomerReceipt $receipt, int $width): string
    {
        $out = $this->bold(true);
        $out .= $this->center($this->fit($receipt->branchName, $width), $width).self::LF;
        $out .= $this->bold(false);

        if ($receipt->branchAddress !== null) {
            $out .= $this->center($this->fit($receipt->branchAddress, $width), $width).self::LF;
        }

        if ($receipt->branchPhone !== null) {
            $out .= $this->center($this->fit($receipt->branchPhone, $width), $width).self::LF;
        }

        $out .= $this->rule($width);

        $origin = $receipt->tableLabel !== null && $receipt->tableLabel !== ''
            ? 'Table '.$receipt->tableLabel
            : $receipt->orderType->label();

        $out .= $this->fit('Recu '.$receipt->reference, $width).self::LF;
        $out .= $this->fit(
            $origin.($receipt->covers !== null ? '  -  '.$receipt->covers.' couv.' : ''),
            $width,
        ).self::LF;
        $out .= $this->rule($width);

        return $out;
    }

    private function body(CustomerReceipt $receipt, int $width): string
    {
        $out = '';

        foreach ($receipt->lines as $line) {
            // Ligne de prix : « 2 x Burger » à gauche, total de ligne à droite.
            $left = $line->quantity.' x '.$line->name;
            $right = $this->money($line->lineTotalMinor, $receipt->currency);

            $out .= $this->twoColumns($left, $right, $width).self::LF;

            // Prix unitaire : utile dès que la quantité dépasse 1 (le client
            // doit pouvoir vérifier le calcul), inutile sinon.
            if ($line->quantity !== '1') {
                $out .= $this->fit(
                    '   @ '.$this->money($line->unitPriceMinor, $receipt->currency),
                    $width,
                ).self::LF;
            }
        }

        return $out;
    }

    private function totals(CustomerReceipt $receipt, int $width): string
    {
        $out = $this->rule($width);

        $out .= $this->twoColumns(
            'Sous-total',
            $this->money($receipt->subtotalMinor, $receipt->currency),
            $width,
        ).self::LF;

        if ($receipt->discountMinor > 0) {
            $out .= $this->twoColumns(
                'Remise',
                '-'.$this->money($receipt->discountMinor, $receipt->currency),
                $width,
            ).self::LF;
        }

        if ($receipt->taxMinor > 0) {
            $out .= $this->twoColumns(
                'TVA',
                $this->money($receipt->taxMinor, $receipt->currency),
                $width,
            ).self::LF;
        }

        $out .= $this->rule($width);

        $out .= $this->bold(true);
        $out .= $this->twoColumns(
            'TOTAL',
            $this->money($receipt->totalMinor, $receipt->currency),
            $width,
        ).self::LF;
        $out .= $this->bold(false);

        $out .= $this->twoColumns(
            'Regle',
            $this->money($receipt->paidMinor, $receipt->currency),
            $width,
        ).self::LF;

        if ($receipt->changeMinor > 0) {
            $out .= $this->twoColumns(
                'Rendu',
                $this->money($receipt->changeMinor, $receipt->currency),
                $width,
            ).self::LF;
        }

        return $out;
    }

    private function footer(CustomerReceipt $receipt, int $width): string
    {
        $out = $this->rule($width);

        // #8610 — un reçu ne confirme JAMAIS un paiement non validé.
        if (! $receipt->isFullyPaid()) {
            $out .= $this->bold(true);
            $out .= $this->center($this->fit('*** NON ACQUITTE ***', $width), $width).self::LF;
            $out .= $this->bold(false);
            $out .= $this->twoColumns(
                'Reste a payer',
                $this->money(max(0, $receipt->totalMinor - $receipt->paidMinor), $receipt->currency),
                $width,
            ).self::LF;
            $out .= $this->rule($width);
        }

        foreach ($receipt->mentions as $mention) {
            $out .= $this->center($this->fit($mention, $width), $width).self::LF;
        }

        $out .= $this->fit('Rev. '.$receipt->revision.'  '.$receipt->printedAt, $width).self::LF;
        $out .= self::LF.self::LF;
        $out .= $this->cut();

        return $out;
    }

    /**
     * Formate un montant en minor units.
     *
     * Aucune division flottante : la partie entière et les centimes sont
     * séparés par arithmétique entière, ce qui évite toute dérive d'arrondi
     * sur un document comptable.
     */
    private function money(int $minor, string $currency): string
    {
        $negative = $minor < 0;
        $absolute = abs($minor);

        $units = intdiv($absolute, 100);
        $cents = $absolute % 100;

        $formatted = number_format($units, 0, '.', ' ').'.'.str_pad((string) $cents, 2, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').$formatted.' '.$currency;
    }

    /**
     * Deux colonnes : texte à gauche, montant aligné à droite.
     *
     * Si le contenu ne tient pas, le montant reste TOUJOURS lisible (il est
     * la donnée comptable) et c'est le libellé qui est tronqué.
     */
    private function twoColumns(string $left, string $right, int $width): string
    {
        $right = trim($right);
        $rightLength = mb_strlen($right);

        if ($rightLength >= $width) {
            return $this->fit($right, $width);
        }

        $available = $width - $rightLength - 1;
        $left = $this->fit($left, max(1, $available));

        $padding = max(1, $width - mb_strlen($left) - $rightLength);

        return $left.str_repeat(' ', $padding).$right;
    }

    private function cut(): string
    {
        return self::ESC.'d'.chr(3).self::GS.'V'.chr(1);
    }

    private function bold(bool $on): string
    {
        return self::ESC.'E'.($on ? chr(1) : chr(0));
    }

    private function center(string $text, int $width): string
    {
        return $this->align(1).$this->fit($text, $width).$this->align(0);
    }

    private function align(int $mode): string
    {
        return self::ESC.'a'.chr($mode);
    }

    private function rule(int $width): string
    {
        return str_repeat('-', $width).self::LF;
    }

    /**
     * Tronque le texte à la largeur papier (sans casser l'UTF-8).
     */
    private function fit(string $text, int $width): string
    {
        $text = trim($text);

        if (mb_strlen($text) <= $width) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, max(1, $width - 1), 'UTF-8')).'.';
    }
}
