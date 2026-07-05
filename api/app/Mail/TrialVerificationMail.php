<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TrialVerificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $managerName,
        public readonly string $verificationToken,
        public readonly string $locale = 'fr',
    ) {}

    public function build(): self
    {
        return $this
            ->subject($this->resolveSubject($this->locale))
            ->view('emails.trial-verification', [
                'managerName' => $this->managerName,
                'verificationToken' => $this->verificationToken,
                'locale' => $this->locale,
            ]);
    }

    private function resolveSubject(string $locale): string
    {
        return match ($locale) {
            'en' => 'Verify your Leopardo RH email',
            'ar' => 'تحقق من بريدك الإلكتروني في Leopardo RH',
            'tr' => 'Leopardo RH e-postanızı doğrulayın',
            default => 'Vérifiez votre email Leopardo RH',
        };
    }
}
