<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Tenant\Domain\Models\Company;
use App\Support\I18nCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

/**
 * #R12 — Rappel d'onboarding J+1.
 *
 * Envoyé par SendOnboardingRemindersCommand aux managers dont la société
 * a été créée 24h avant mais dont l'onboarding n'est pas complété.
 * Suit le même pattern que les drip trials (TrialDayOneMail).
 */
class OnboardingReminderMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly string  $managerName,
        public readonly string  $managerEmail,
        ?string $locale = null,
    ) {
        $this->locale = I18nCatalog::normalizeLocale($locale ?? $company->language);
    }

    public function envelope(): Envelope
    {
        App::setLocale($this->locale);

        return new Envelope(
            subject: __('emails.onboarding_reminder_subject'),
        );
    }

    public function content(): Content
    {
        App::setLocale($this->locale);

        return new Content(
            view: 'emails.onboarding.reminder',
            with: [
                'company'     => $this->company,
                'managerName' => $this->managerName,
                'setupUrl'    => config('app.url') . '/',
                'locale'      => $this->locale,
            ],
        );
    }
}
