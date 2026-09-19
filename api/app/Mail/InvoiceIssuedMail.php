<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Infrastructure\Services\InvoicePdfRenderer;
use App\Support\I18nCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

/**
 * Facture émise (#7763) — envoyée au principal du tenant lors de la
 * génération mensuelle (`billing:generate-invoices`), avec la facture PDF en
 * pièce jointe (même rendu que `GET /billing/invoices/{id}/pdf`, factorisé
 * dans {@see InvoicePdfRenderer}).
 */
class InvoiceIssuedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly Company $company,
        public readonly Employee $recipient,
    ) {
        // Envoi en queue : le middleware SetLocale ne s'applique jamais ici —
        // la locale du destinataire est résolue explicitement (même pattern
        // que UserInvitationMail / RoleAssignmentMail).
        $this->locale = I18nCatalog::normalizeLocale(
            $this->recipient->preferred_language ?? $this->company->language
        );
    }

    public function build(): self
    {
        App::setLocale($this->locale);

        $tpl = app(\App\Core\Mail\EmailTemplateResolver::class)->resolve('invoice_issued', $this->locale, [
            ':company' => $this->company->name,
            ':number' => $this->invoice->number ?? '',
            ':total' => $this->invoice->total,
            ':currency' => $this->invoice->currency,
            ':due_date' => $this->invoice->due_date->format('d/m/Y'),
            ':name' => $this->recipient->first_name ?? '',
            ':brand' => \App\Core\Mail\MailBrand::name(),
        ]);

        $pdf = app(InvoicePdfRenderer::class)->render($this->invoice, $this->company);

        return $this
            ->subject($tpl->subject)
            ->view('emails.invoice-issued', ['locale' => $this->locale, 'tpl' => $tpl])
            ->attachData($pdf['content'], $pdf['filename'], ['mime' => 'application/pdf']);
    }
}
