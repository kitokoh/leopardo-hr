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
            subject: 'Réinitialisation de votre mot de passe Leopardo',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.password-reset',
            with: [
                'token' => $this->token,
                'email' => $this->email,
            ],
        );
    }
}
