<?php

namespace App\Mail;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TrialWelcomeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly Employee $manager,
        public readonly string $tempPassword,
    ) {}

    public function build(): self
    {
        $locale = $this->company->language ?? 'fr';

        // S-5 (#1665) : les vues résolvent leurs chaînes via __() — il faut
        // épingler la locale applicative AVANT le rendu, sinon le corps du
        // mail se rend dans la locale ambiante (Accept-Language / défaut) et
        // le sujet/le corps peuvent être dans des langues différentes.
        \Illuminate\Support\Facades\App::setLocale($locale);

        return $this
            ->subject($this->resolveSubject($locale))
            ->view('emails.trial-welcome', [
                'company' => $this->company,
                'manager' => $this->manager,
                'tempPassword' => $this->tempPassword,
                'locale' => $locale,
                'trialDays' => 30,
            ]);
    }

    private function resolveSubject(string $locale): string
    {
        return match ($locale) {
            'en' => 'Your Leopardo RH workspace is ready!',
            'ar' => 'مساحة عملك في Leopardo RH جاهزة!',
            'tr' => 'Leopardo RH çalışma alanınız hazır!',
            default => 'Votre espace Leopardo RH est prêt !',
        };
    }
}

