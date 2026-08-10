<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
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

    public function build(): self
    {
        $locale = $this->company->language ?? 'fr';

        // S-5 (#1665) : épingler la locale applicative AVANT le rendu (le
        // worker de queue n'a pas la locale du tenant par défaut).
        \Illuminate\Support\Facades\App::setLocale($locale);

        return $this
            ->subject(__('mail.subscription_confirmed.subject'))
            ->markdown('emails.subscription-confirmed', [
                'companyName' => $this->company->name,
                'plan' => $this->subscription->plan,
                'periodEnd' => $this->subscription->current_period_end,
                'invoiceUrl' => $this->invoiceUrl,
                'dashboardUrl' => config('app.frontend_url').'/dashboard',
                'locale' => $locale,
            ]);
    }
}

