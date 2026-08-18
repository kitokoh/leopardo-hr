<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Issue #2626 — email de réinitialisation de mot de passe.
 * Le token est envoyé en clair (usage unique, 60 min) ; seul son hash est
 * stocké en base.
 *
 * i18n (audit S-5 #1665, résiduel 2026-08-17) : sujet et corps résolus via
 * les catalogues `emails.email_password_reset_*` (fr/en/ar/tr). La locale
 * applicative est celle posée par SetLocale au moment de l'envoi (requête
 * API) ; les clés existent dans les 4 catalogues.
 */
class PasswordResetMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $token,
        public readonly string $email,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.email_password_reset_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.password-reset',
            with: [
                'token' => $this->token,
                'email' => $this->email,
                'userName' => $this->email,
            ],
        );
    }
}
