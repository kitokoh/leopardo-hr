<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Printing;

use App\Modules\RestaurantManager\Domain\Contracts\OrderTicketRendererInterface;
use App\Modules\RestaurantManager\Domain\ValueObjects\KitchenTicket;

/**
 * Rend un ticket cuisine en flux ESC/POS pour imprimante thermique 58/80 mm
 * (REST-OS-1, Mission Leopardo Restaurant OS).
 *
 * Classe PURE : aucune I/O, aucun accès Eloquent — testable sans base ni
 * matériel. Le bridge d'impression local (REST-OS-3) transmet le flux produit
 * au poste de cuisine (ESC/POS via USB / réseau 9100 / CUPS).
 *
 * L'accès au matériel dépend de l'OS, du driver et du bridge : c'est pourquoi
 * le rendu est isolé du transport, et pourquoi l'impression n'est jamais
 * considérée réussie sans accusé du bridge (voir PrintJobStatus::PRINTED).
 */
final class EscPosOrderTicketRenderer implements OrderTicketRendererInterface
{
    private const ESC = "\x1B";

    private const GS = "\x1D";

    private const LF = "\n";

    private const CHARS_PER_LINE_58 = 32;

    private const CHARS_PER_LINE_80 = 48;

    public function render(KitchenTicket $ticket, int $paperWidth = 80): string
    {
        $width = $paperWidth <= 58 ? self::CHARS_PER_LINE_58 : self::CHARS_PER_LINE_80;

        return $this->init()
            .$this->header($ticket, $width)
            .$this->body($ticket, $width)
            .$this->footer($ticket, $width);
    }

    private function init(): string
    {
        // Reset, encodage PC437 (accents FR), alignement gauche.
        return self::ESC.'@'.self::ESC.'t'.chr(0).self::ESC.'a'.chr(0);
    }

    private function header(KitchenTicket $ticket, int $width): string
    {
        $out = $this->bold(true);
        $out .= $this->center($this->fit('CUISINE', $width), $width).self::LF;
        $out .= $this->center($this->fit($ticket->branchName, $width), $width).self::LF;
        $out .= $this->bold(false);
        $out .= $this->rule($width);

        $origin = $ticket->tableLabel !== null && $ticket->tableLabel !== ''
            ? 'Table '.$ticket->tableLabel
            : $ticket->orderType->label();

        $out .= $this->fit('Cde '.$ticket->reference, $width).self::LF;
        $out .= $this->fit(
            $origin.($ticket->covers !== null ? '  -  '.$ticket->covers.' couv.' : ''),
            $width,
        ).self::LF;
        $out .= $this->rule($width);

        return $out;
    }

    private function body(KitchenTicket $ticket, int $width): string
    {
        $out = '';

        foreach ($ticket->lines as $line) {
            $qty = $line->quantity;
            $name = $line->name;
            $prefix = $line->isCancelled ? 'ANNUL ' : '';

            $out .= $this->fit($prefix.$qty.' x '.$name, $width).self::LF;

            if ($line->note !== null && $line->note !== '') {
                $out .= $this->fit('   > '.$line->note, $width).self::LF;
            }
        }

        if ($ticket->note !== null && $ticket->note !== '') {
            $out .= $this->rule($width);
            $out .= $this->fit('Note: '.$ticket->note, $width).self::LF;
        }

        return $out;
    }

    private function footer(KitchenTicket $ticket, int $width): string
    {
        return $this->rule($width)
            .$this->fit('Rev. '.$ticket->revision.'  '.$ticket->printedAt, $width).self::LF
            .self::LF.self::LF
            .$this->cut();
    }

    private function cut(): string
    {
        // Avance papier + coupe partielle (bridge/compatibilité : la coupe est
        // ignorée par les modèles sans cutter, sans casser le flux).
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
