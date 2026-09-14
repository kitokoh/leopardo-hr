<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * #7347 — le sujet n'est plus codé en dur dans 4 langues (il portait aussi la
 * marque en dur) : il vient du registre d'e-mails, donc du catalogue, et devient
 * modifiable depuis l'admin (Paramètres › E-mails).
 */
class TrialVerificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $managerName,
        public readonly string $verificationToken,
        public readonly string $emailLocale = 'fr',
    ) {}

    public function build(): self
    {
        return $this
            // La locale pilote la résolution de `__()` dans la vue : sans elle,
            // les chaînes du catalogue `api/lang` retombaient sur la locale de
            // l'application (français/anglais) alors que le sujet était déjà
            // localisé — e-mail hybride.
            ->locale($this->emailLocale)
            ->subject(app(\App\Core\Mail\EmailTemplateResolver::class)->resolve('trial_verification', $this->emailLocale, [
                ':name' => $this->managerName,
                ':brand' => config('mail.brand.name'),
            ])->subject)
            ->view('emails.trial-verification', [
                'managerName' => $this->managerName,
                'verificationToken' => $this->verificationToken,
                'locale' => $this->emailLocale,
                'tpl' => app(\App\Core\Mail\EmailTemplateResolver::class)->resolve('trial_verification', $this->emailLocale, [
                    ':name' => $this->managerName,
                    ':brand' => \App\Core\Mail\MailBrand::name(),
                ]),
            ]);
    }
}
