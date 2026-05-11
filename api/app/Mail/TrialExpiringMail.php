<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TrialExpiringMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly Company $company,
        public readonly int $daysLeft,
        public readonly string $locale = 'fr',
    ) {}

    public function build(): self
    {
        $subjects = [
            'fr' => "Votre essai Leopardo RH expire dans {$this->daysLeft} jour(s)",
            'en' => "Your Leopardo RH trial expires in {$this->daysLeft} day(s)",
            'ar' => "تنتهي تجربتك في Leopardo RH خلال {$this->daysLeft} يوم (أيام)",
        ];

        return $this
            ->subject($subjects[$this->locale] ?? $subjects['fr'])
            ->view('emails.trial-expiring')
            ->with(['locale' => $this->locale]);
    }
}
