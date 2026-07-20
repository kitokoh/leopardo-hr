<?php

namespace App\Mail;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Support\I18nCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class TrialDripMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Company $company,
        public Employee $manager,
        public string $type
    ) {
        // Dispatched from the app:send-drip-emails console command, which
        // runs outside any HTTP request/middleware pipeline. The dummy
        // manager built by the command never has preferred_language set, so
        // fall back to the company's configured language.
        $this->locale = I18nCatalog::normalizeLocale(
            $manager->preferred_language ?? $company->language
        );
    }

    public function build()
    {
        App::setLocale($this->locale);

        $appName = config('app.name');

        $subject = match ($this->type) {
            'day3' => __('emails.trial_day3_subject', ['appName' => $appName]),
            'expiring' => __('emails.trial_expiring_subject', ['appName' => $appName]),
            'expired' => __('emails.trial_expired_subject', ['appName' => $appName]),
            default => __('emails.trial_drip_default_subject', ['appName' => $appName]),
        };

        return $this->subject($subject)
            ->view("emails.trial.{$this->type}")
            ->with([
                'companyName' => $this->company->name,
                'managerName' => $this->manager->first_name,
                'appName' => $appName,
                'appUrl' => config('app.frontend_url', 'http://localhost:3000'),
                'locale' => $this->locale,
            ]);
    }
}

