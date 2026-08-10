<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\User;
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
        public readonly string $emailLocale = 'fr',
    ) {}

    public function build(): self
    {
        $subjects = [
            'fr' => 'Bienvenue sur Leopardo RH — Démarrez maintenant',
            'en' => 'Welcome to Leopardo RH — Get Started',
            'ar' => 'مرحبًا بك في Leopardo RH — ابدأ الآن',
            'tr' => 'Leopardo RH çalışma alanınıza hoş geldiniz — Başlayın',
        ];

        // S-5 (#1665) : épingler la locale pour que le rendu de la vue
        // (__()) soit dans la langue de l'entreprise, pas la locale ambiante.
        \Illuminate\Support\Facades\App::setLocale($this->emailLocale);

        return $this
            ->subject($subjects[$this->emailLocale] ?? $subjects['fr'])
            ->view('emails.welcome-onboarding')
            ->with(['locale' => $this->emailLocale]);
    }
}

