<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TrialDripMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Company $company,
        public Employee $manager,
        public string $type
    ) {}

    public function build()
    {
        $subject = match ($this->type) {
            'day3' => 'Comment ajouter vos employés sur Leopardo',
            'expiring' => 'Votre essai Leopardo expire dans 3 jours',
            'expired' => 'Votre essai Leopardo est terminé',
            default => 'Nouvelle notification Leopardo',
        };

        return $this->subject($subject)
            ->view("emails.trial.{$this->type}")
            ->with([
                'companyName' => $this->company->name,
                'managerName' => $this->manager->first_name,
                'appName' => config('app.name'),
                'appUrl' => config('app.frontend_url', 'http://localhost:3000'),
            ]);
    }
}
