<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\Employee;
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
            'tr' => 'Leopardo RH calisma alaniniz hazir!',
            default => 'Votre espace Leopardo RH est prêt !',
        };
    }
}
