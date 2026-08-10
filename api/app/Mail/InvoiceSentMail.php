<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Invoice;
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
        public readonly string $emailLocale = 'fr',
    ) {}

    public function build(): self
    {
        $subjects = [
            'fr' => 'Votre facture Leopardo RH — '.$this->invoice->number,
            'en' => 'Your Leopardo RH Invoice — '.$this->invoice->number,
            'ar' => 'فاتورتك من Leopardo RH — '.$this->invoice->number,
        ];

        return $this
            ->subject($subjects[$this->emailLocale] ?? $subjects['fr'])
            ->view('emails.invoice-sent')
            ->with(['locale' => $this->emailLocale]);
    }
}
