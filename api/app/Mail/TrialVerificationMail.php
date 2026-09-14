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
        public readonly string $emailLocale = 'fr',
    ) {
        // `Mailable::send()` évalue `withLocale($this->locale)` AVANT d'appeler
        // `build()` : une locale posée dans `build()` (ce que faisait le code
        // précédent) arrive donc TROP TARD pour la vue, qui se rend dans la
        // locale ambiante de l'application. Résultat mesuré le 2026-09-14 :
        // sujet turc et corps français dans le même e-mail. On épingle la
        // locale ici, avant tout rendu.
        $this->locale($this->emailLocale);
    }

    public function build(): self
    {
        return $this
            // La locale est déjà épinglée par le constructeur (cf. ci-dessus) :
            // ne pas la reposer ici, ce serait trop tard pour la vue.
            ->subject($this->resolveSubject($this->emailLocale))
            ->view('emails.trial-verification', [
                'managerName' => $this->managerName,
                'verificationToken' => $this->verificationToken,
                'locale' => $this->emailLocale,
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
