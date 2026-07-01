<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation email sent after a successful subscription payment.
 */
class SubscriptionConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly Subscription $subscription,
        public readonly string $invoiceUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.subscription_confirmed.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.subscription-confirmed',
            with: [
                'companyName' => $this->company->name,
                'plan' => $this->subscription->plan,
                'periodEnd' => $this->subscription->current_period_end,
                'invoiceUrl' => $this->invoiceUrl,
                'dashboardUrl' => config('app.frontend_url').'/dashboard',
            ],
        );
    }
}
