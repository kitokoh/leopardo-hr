<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * #7490 — code de connexion à usage unique (OTP) pour les comptes qui n'ont
 * jamais défini de mot de passe. Même canal e-mail que la vérification
 * d'inscription (TrialVerificationMail) : registre de templates + catalogue
 * `api/lang/*/emails.php`, ×4 locales.
 *
 * Aucun secret durable dans cet e-mail : le code expire en 10 minutes et est
 * consommé au premier usage.
 */
class LoginCodeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $loginCode,
        public readonly string $emailLocale = 'fr',
    ) {}

    public function build(): self
    {
        return $this
            // La locale pilote la résolution de `__()` dans la vue (même
            // convention que TrialVerificationMail, #7347).
            ->locale($this->emailLocale)
            ->subject(app(\App\Core\Mail\EmailTemplateResolver::class)->resolve('login_code', $this->emailLocale, [
                ':brand' => config('mail.brand.name'),
            ])->subject)
            ->view('emails.login-code', [
                'loginCode' => $this->loginCode,
                'locale' => $this->emailLocale,
                'tpl' => app(\App\Core\Mail\EmailTemplateResolver::class)->resolve('login_code', $this->emailLocale, [
                    ':brand' => \App\Core\Mail\MailBrand::name(),
                ]),
            ]);
    }
}
