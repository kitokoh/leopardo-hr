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
 * Drip email J+1 — Welcome & first steps after trial signup.
 */
class TrialDayOneMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly string $managerName,
        public readonly string $managerEmail,
        ?string $locale = null,
    ) {
        // Dispatched from a queued job (no HTTP request/middleware), so
        // resolve the locale explicitly instead of relying on SetLocale.
        $this->locale = I18nCatalog::normalizeLocale($locale ?? $company->language);
    }

    public function envelope(): Envelope
    {
        App::setLocale($this->locale);

        return new Envelope(
            subject: '🦁 '.__('emails.trial_day1_subject'),
        );
    }

    public function content(): Content
    {
        App::setLocale($this->locale);

        // #7238 — domaine PRODUIT (portail) : un CTA d'e-mail ouvre l'UI,
        // jamais l'API (même convention que TrialWelcomeMail).
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return new Content(
            markdown: 'emails.trial.day_one',
            with: [
                'company' => $this->company,
                'managerName' => $this->managerName,
                'loginUrl' => $base.'/auth/login',
                'docsUrl' => $base.'/docs',
                'locale' => $this->locale,
            ],
        );
    }
}
