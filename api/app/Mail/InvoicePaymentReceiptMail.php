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
 * Reçu de paiement (#7763) — envoyé au principal du tenant quand une facture
 * transite vers `paid` (point unique : événement {@see \App\Events\InvoicePaid}
 * dispatché par `Invoice::transitionTo`, quel que soit le provider Stripe ou
 * Chargily). La facture acquittée est jointe en PDF.
 */
class InvoicePaymentReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly Company $company,
        public readonly Employee $recipient,
    ) {
        $this->locale = I18nCatalog::normalizeLocale(
            $this->recipient->preferred_language ?? $this->company->language
        );
    }

    public function build(): self
    {
        App::setLocale($this->locale);

        $tpl = app(\App\Core\Mail\EmailTemplateResolver::class)->resolve('invoice_payment_receipt', $this->locale, [
            ':company' => $this->company->name,
            ':number' => $this->invoice->number ?? '',
            ':total' => $this->invoice->total,
            ':currency' => $this->invoice->currency,
            ':paid_at' => $this->invoice->paid_at?->format('d/m/Y') ?? now()->format('d/m/Y'),
            ':name' => $this->recipient->first_name ?? '',
            ':brand' => \App\Core\Mail\MailBrand::name(),
        ]);

        $pdf = app(InvoicePdfRenderer::class)->render($this->invoice, $this->company);

        return $this
            ->subject($tpl->subject)
            ->view('emails.invoice-payment-receipt', ['locale' => $this->locale, 'tpl' => $tpl])
            ->attachData($pdf['content'], $pdf['filename'], ['mime' => 'application/pdf']);
    }
}
