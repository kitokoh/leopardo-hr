<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceSentMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly Company $company,
        public readonly string $locale = 'fr',
    ) {}

    public function build(): self
    {
        $subjects = [
            'fr' => 'Votre facture Leopardo RH — '.$this->invoice->invoice_number,
            'en' => 'Your Leopardo RH Invoice — '.$this->invoice->invoice_number,
            'ar' => 'فاتورتك من Leopardo RH — '.$this->invoice->invoice_number,
        ];

        return $this
            ->subject($subjects[$this->locale] ?? $subjects['fr'])
            ->view('emails.invoice-sent')
            ->with(['locale' => $this->locale]);
    }
}
