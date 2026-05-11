<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeOnboardingMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Company $company,
        public readonly string $locale = 'fr',
    ) {}

    public function build(): self
    {
        $subjects = [
            'fr' => 'Bienvenue sur Leopardo RH — Démarrez maintenant',
            'en' => 'Welcome to Leopardo RH — Get Started',
            'ar' => 'مرحبًا بك في Leopardo RH — ابدأ الآن',
        ];

        return $this
            ->subject($subjects[$this->locale] ?? $subjects['fr'])
            ->view('emails.welcome-onboarding')
            ->with(['locale' => $this->locale]);
    }
}
