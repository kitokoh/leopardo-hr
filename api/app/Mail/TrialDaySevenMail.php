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
 * Drip email J+7 — Convert trial to paid plan.
 */
class TrialDaySevenMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly string  $managerName,
        public readonly int     $employeeCount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🦁 Leopardo RH — Votre essai se termine bientôt',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.trial.day_seven',
            with: [
                'company'       => $this->company,
                'managerName'   => $this->managerName,
                'employeeCount' => $this->employeeCount,
                'pricingUrl'    => config('app.url') . '/pricing',
                'upgradeUrl'    => config('app.url') . '/billing/upgrade',
            ],
        );
    }
}
