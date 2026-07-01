<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation email sent to a person to join a company on Leopardo RH.
 */
class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $inviteeEmail,
        public readonly string $inviterName,
        public readonly string $companyName,
        public readonly string $invitationUrl,
        public readonly ?string $role = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.invitation.subject', ['company' => $this->companyName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invitation',
            with: [
                'inviterName' => $this->inviterName,
                'companyName' => $this->companyName,
                'invitationUrl' => $this->invitationUrl,
                'role' => $this->role,
                'inviteeEmail' => $this->inviteeEmail,
            ],
        );
    }
}
