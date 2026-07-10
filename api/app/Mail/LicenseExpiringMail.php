<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alert email sent when an Edge node license is about to expire.
 */
class LicenseExpiringMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly \Carbon\Carbon $expiresAt,
        public readonly int $daysRemaining,
        public readonly string $renewUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.license_expiring.subject', ['days' => $this->daysRemaining]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.license-expiring',
            with: [
                'companyName' => $this->company->name,
                'expiresAt' => $this->expiresAt->format('d/m/Y'),
                'daysRemaining' => $this->daysRemaining,
                'renewUrl' => $this->renewUrl,
            ],
        );
    }
}

