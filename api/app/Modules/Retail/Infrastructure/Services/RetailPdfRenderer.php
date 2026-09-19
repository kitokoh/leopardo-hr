<?php

declare(strict_types=1);

namespace App\Modules\Retail\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderItem;
use App\Modules\Retail\Domain\Models\RetailOrderPayment;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Rendu PDF des documents de vente Retail (BC-17, #7813) — pattern
 * `InvoicePdfRenderer` du module Billing (#7763) : DomPDF + template Blade,
 * une seule source de rendu par document.
 *
 * - Recu de caisse (`pdf.retail-pos-receipt`) : format ticket 80 mm
 *   (226.77 pt de large, hauteur genereuse — dompdf ne coupe pas la page
 *   sur un contenu court) ;
 * - Facture (`pdf.retail-invoice`) : A4 portrait, numero legal OBLIGATOIRE
 *   (attribue en amont par RetailInvoiceService — le renderer ne numerote
 *   jamais lui-meme).
 */
class RetailPdfRenderer
{
    /**
     * @return array{content: string, filename: string}
     */
    public function renderReceipt(RetailOrder $order, ?Company $company = null): array
    {
        $pdf = Pdf::loadView('pdf.retail-pos-receipt', [
            'order' => $order,
            'items' => $this->items($order),
            'payments' => $this->payments($order),
            'company' => $company,
        ]);

        // Ticket 80 mm : 80 mm = 226.77 pt ; hauteur fixe large (le rouleau
        // est coupe a l'impression).
        $pdf->setPaper([0, 0, 226.77, 850]);

        return [
            'content' => $pdf->output(),
            'filename' => sprintf('recu_%s.pdf', $order->reference),
        ];
    }

    /**
     * @return array{content: string, filename: string}
     */
    public function renderInvoice(RetailOrder $order, ?Company $company = null): array
    {
        $pdf = Pdf::loadView('pdf.retail-invoice', [
            'order' => $order,
            'items' => $this->items($order),
            'payments' => $this->payments($order),
            'company' => $company,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return [
            'content' => $pdf->output(),
            'filename' => sprintf('facture_%s.pdf', $order->invoice_number ?? $order->reference),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, RetailOrderItem>
     */
    private function items(RetailOrder $order): \Illuminate\Support\Collection
    {
        return $order->items()->orderBy('line_index')->get()->toBase();
    }

    /**
     * @return \Illuminate\Support\Collection<int, RetailOrderPayment>
     */
    private function payments(RetailOrder $order): \Illuminate\Support\Collection
    {
        return $order->payments()->orderBy('id')->get()->toBase();
    }
}
