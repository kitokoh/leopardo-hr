<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Mail\InvoiceIssuedMail;
use App\Mail\InvoicePaymentReceiptMail;
use App\Modules\Billing\Domain\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envoi des emails de facturation plateforme (#7763).
 *
 * Un seul point de sortie pour « facture émise » (génération mensuelle) et
 * « reçu de paiement » (transition unique `Invoice::transitionTo(Paid)`).
 * Le destinataire est le PRINCIPAL du tenant : l'employé `manager_role =
 * 'principal'` de la company (même identification que les emails de trial —
 * cf. SendDripEmails / SendOnboardingRemindersCommand). Sans principal ou
 * sans email, on journalise et on n'envoie rien : un email manquant ne doit
 * JAMAIS faire échouer la génération de factures ni un webhook de paiement.
 */
class InvoiceMailer
{
    public function sendInvoiceIssued(Invoice $invoice): void
    {
        $this->send($invoice, InvoiceIssuedMail::class);
    }

    public function sendPaymentReceipt(Invoice $invoice): void
    {
        $this->send($invoice, InvoicePaymentReceiptMail::class);
    }

    /**
     * @param  class-string<InvoiceIssuedMail|InvoicePaymentReceiptMail>  $mailable
     */
    private function send(Invoice $invoice, string $mailable): void
    {
        $company = $this->resolveCompany($invoice);

        if (! $company instanceof Company) {
            Log::warning('Billing: email de facture non envoyé — company introuvable', [
                'invoice_id' => $invoice->id,
                'company_id' => $invoice->company_id,
                'mailable' => $mailable,
            ]);

            return;
        }

        $principal = $this->resolvePrincipal($company);

        if (! $principal instanceof Employee || $principal->email === '') {
            Log::warning('Billing: email de facture non envoyé — aucun principal avec email', [
                'invoice_id' => $invoice->id,
                'company_id' => $company->id,
                'mailable' => $mailable,
            ]);

            return;
        }

        // Mailables ShouldQueue : Mail::to()->send() pousse en queue.
        Mail::to($principal->email)->send(new $mailable($invoice, $company, $principal));
    }

    /**
     * Lecture QUALIFIÉE de `public.companies` (DEP-BC21 #6246) : l'envoi peut
     * être déclenché hors contexte tenant (webhook, console).
     */
    private function resolveCompany(Invoice $invoice): ?Company
    {
        if ($invoice->company_id === null) {
            return null;
        }

        return Company::query()
            ->from(DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies')
            ->find($invoice->company_id);
    }

    private function resolvePrincipal(Company $company): ?Employee
    {
        return Employee::query()
            ->where('company_id', $company->id)
            ->where('manager_role', 'principal')
            ->orderBy('id')
            ->first();
    }
}
