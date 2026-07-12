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
 * Drip email J+3 — Tips: pointage, absences, exports.
 */
class TrialDayThreeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly string  $managerName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🦁 Leopardo RH — Avez-vous essayé le pointage mobile ?',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.trial.day_three',
            with: [
                'company'       => $this->company,
                'managerName'   => $this->managerName,
                'checkInUrl'    => config('app.url') . '/attendance',
                'mobileAppsUrl' => config('app.url') . '/download',
            ],
        );
    }
}
