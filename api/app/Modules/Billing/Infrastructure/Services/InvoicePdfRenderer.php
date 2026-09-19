<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * Rendu PDF d'une facture plateforme (#7763).
 *
 * Factorisation du code de `BillingController::invoicePdf` : le MÊME template
 * (`resources/views/pdf/invoice.blade.php`, DomPDF, A4 portrait) sert au
 * téléchargement HTTP ET à la pièce jointe des emails de facture/reçu — un
 * seul rendu canonique, zéro divergence entre le PDF téléchargé et celui reçu
 * par email.
 */
class InvoicePdfRenderer
{
    /**
     * @return array{content: string, filename: string}
     */
    public function render(Invoice $invoice, ?Company $company = null): array
    {
        $company ??= $this->resolveCompany($invoice);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $company,
            'legalMentions' => '',
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = sprintf('facture_%s.pdf',
            $invoice->number ?? 'LEO-'.now()->format('Y').'-'.str_pad((string) $invoice->id, 4, '0', STR_PAD_LEFT)
        );

        return [
            'content' => $pdf->output(),
            'filename' => $filename,
        ];
    }

    /**
     * Lecture QUALIFIÉE de `public.companies` (même garde que StripeService,
     * DEP-BC21 #6246) : le rendu peut être déclenché hors contexte tenant
     * (webhook, commande console, worker de queue) où le search_path n'est
     * pas garanti sur `public`.
     */
    private function resolveCompany(Invoice $invoice): ?Company
    {
        if ($invoice->company_id === null) {
            return null;
        }

        $company = Company::query()
            ->from(DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies')
            ->find($invoice->company_id);

        return $company instanceof Company ? $company : null;
    }
}
