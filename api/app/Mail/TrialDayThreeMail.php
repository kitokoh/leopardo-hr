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
 * Drip email J+3 — Tips: pointage, absences, exports.
 */
class TrialDayThreeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly string  $managerName,
        ?string $locale = null,
    ) {
        $this->locale = I18nCatalog::normalizeLocale($locale ?? $company->language);
    }

    public function envelope(): Envelope
    {
        App::setLocale($this->locale);

        return new Envelope(
            subject: '🦁 '.__('emails.trial_day3_mail_subject'),
        );
    }

    public function content(): Content
    {
        App::setLocale($this->locale);

        // #7238 — domaine PRODUIT (portail) : un CTA d'e-mail ouvre l'UI,
        // jamais l'API (même convention que TrialWelcomeMail).
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return new Content(
            markdown: 'emails.trial.day_three',
            with: [
                'company'       => $this->company,
                'managerName'   => $this->managerName,
                'checkInUrl'    => $base . '/attendance',
                'mobileAppsUrl' => $base . '/download',
                'locale'        => $this->locale,
            ],
        );
    }
}
