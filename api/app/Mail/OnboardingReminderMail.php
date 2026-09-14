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
        public readonly string $managerName,
        public readonly string $managerEmail,
        ?string $locale = null,
    ) {
        $this->locale = I18nCatalog::normalizeLocale($locale ?? $company->language);
    }

    public function envelope(): Envelope
    {
        App::setLocale($this->locale);

        return new Envelope(
            subject: app(\App\Core\Mail\EmailTemplateResolver::class)->resolve('onboarding_reminder', $this->locale, [
                ':name' => $this->managerName,
                ':company' => $this->company->name,
                ':brand' => config('mail.brand.name'),
            ])->subject,
        );
    }

    public function content(): Content
    {
        App::setLocale($this->locale);

        // #7238 — domaine PRODUIT (portail) : un CTA d'e-mail ouvre l'UI,
        // jamais l'API (même convention que TrialWelcomeMail).
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return new Content(
            view: 'emails.onboarding.reminder',
            with: [
                'company' => $this->company,
                'managerName' => $this->managerName,
                'setupUrl' => $base.'/',
                'locale' => $this->locale,
                'tpl' => app(\App\Core\Mail\EmailTemplateResolver::class)->resolve('onboarding_reminder', $this->locale, [
                    ':name' => $this->managerName,
                    ':company' => $this->company->name,
                    ':brand' => \App\Core\Mail\MailBrand::name(),
                ]),
            ],
        );
    }
}
