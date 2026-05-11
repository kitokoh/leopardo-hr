<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentFailedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $locale = 'fr',
    ) {}

    public function build(): self
    {
        $subjects = [
            'fr' => 'Échec de paiement — Leopardo RH',
            'en' => 'Payment Failed — Leopardo RH',
            'ar' => 'فشل الدفع — Leopardo RH',
        ];

        return $this
            ->subject($subjects[$this->locale] ?? $subjects['fr'])
            ->view('emails.payment-failed')
            ->with(['locale' => $this->locale]);
    }
}
