<?php

declare(strict_types=1);

namespace App\Modules\Retail\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderItem;
use App\Modules\Retail\Domain\Models\RetailOrderPayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * Rendu PDF des documents de vente Retail (BC-17 RETAIL, #7813).
 *
 * Même mécanique canonique que le renderer Billing (`InvoicePdfRenderer`
 * #7763) et Payroll (`PaySlipPdfGenerator`) : DomPDF + template Blade —
 * PAS d'import cross-module (garde #5584), le module rend SES documents
 * avec SES templates :
 *  - `pdf.retail-receipt` : ticket de caisse imprimable (rouleau 80 mm) ;
 *  - `pdf.retail-invoice` : facture A4 (numérotation légale par tenant,
 *    `RetailInvoiceNumberService`).
 */
class RetailDocumentPdfRenderer
{
    /**
     * Ticket POS imprimable (largeur 80 mm ≈ 226,77 pt).
     *
     * @return array{content: string, filename: string}
     */
    public function renderReceipt(RetailOrder $order, ?Company $company = null): array
    {
        $pdf = Pdf::loadView('pdf.retail-receipt', $this->viewData($order, $company));

        // Rouleau thermique 80 mm — hauteur généreuse, le contenu coupe court.
        $pdf->setPaper([0, 0, 226.77, 850.39]);

        return [
            'content' => $pdf->output(),
            'filename' => sprintf('ticket_%s.pdf', $order->reference),
        ];
    }

    /**
     * Facture A4 (le numéro légal DOIT déjà être assigné —
     * RetailInvoiceNumberService::assign).
     *
     * @return array{content: string, filename: string}
     */
    public function renderInvoice(RetailOrder $order, ?Company $company = null): array
    {
        $pdf = Pdf::loadView('pdf.retail-invoice', $this->viewData($order, $company));

        $pdf->setPaper('A4', 'portrait');

        return [
            'content' => $pdf->output(),
            'filename' => sprintf('facture_%s.pdf', $order->invoice_number ?? $order->reference),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(RetailOrder $order, ?Company $company): array
    {
        $company ??= $this->resolveCompany($order);

        $items = RetailOrderItem::query()
            ->where('company_id', (string) $order->company_id)
            ->where('order_id', (int) $order->id)
            ->orderBy('line_index')
            ->get();

        $payments = RetailOrderPayment::query()
            ->where('company_id', (string) $order->company_id)
            ->where('order_id', (int) $order->id)
            ->where('status', 'captured')
            ->orderBy('id')
            ->get();

        return [
            'order' => $order,
            'items' => $items,
            'payments' => $payments,
            'company' => $company,
            'capturedMinor' => (int) $payments->sum('amount_minor'),
        ];
    }

    /**
     * Lecture QUALIFIÉE de `public.companies` (même garde que
     * InvoicePdfRenderer #7763) : le rendu peut être déclenché hors contexte
     * tenant strict où le search_path n'est pas garanti sur `public`.
     */
    private function resolveCompany(RetailOrder $order): ?Company
    {
        $company = Company::query()
            ->from(DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies')
            ->find($order->company_id);

        return $company instanceof Company ? $company : null;
    }
}
