<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Drip email J+1 — Welcome & first steps after trial signup.
 */
class TrialDayOneMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly string  $managerName,
        public readonly string  $managerEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🦁 Bienvenue sur Leopardo RH — Vos premières étapes',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.trial.day_one',
            with: [
                'company'     => $this->company,
                'managerName' => $this->managerName,
                'loginUrl'    => config('app.url') . '/auth/login',
                'docsUrl'     => config('app.url') . '/docs',
            ],
        );
    }
}
