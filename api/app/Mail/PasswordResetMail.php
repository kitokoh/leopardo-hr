<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Audit expert 2026-08-15 (issue #2626) — email de réinitialisation de mot de
 * passe. Réutilise les clés i18n `email_password_reset_*` déjà présentes dans
 * les 4 locales (fr/en/ar/tr). Le lien pointe vers le portail client
 * (`config('app.frontend_url')` ou `app.url` en repli) ; la page de saisie du
 * nouveau mot de passe est un follow-up frontend documenté.
 */
class PasswordResetMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $resetUrl,
    ) {}

    public function build(): self
    {
        $body = __('emails.email_password_reset_body')
            ."\n\n".$this->resetUrl
            ."\n\n".__('emails.email_password_reset_ignore');

        return $this
            ->subject(__('emails.email_password_reset_subject'))
            ->view('emails.communication', [
                'bodyText' => $body,
                'unsubscribeUrl' => null,
            ]);
    }
}
