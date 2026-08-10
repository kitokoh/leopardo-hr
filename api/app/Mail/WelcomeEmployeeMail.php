<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Welcome email sent to a new employee upon onboarding.
 */
class WelcomeEmployeeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Employee $employee,
        public readonly string $loginUrl,
        public readonly string $temporaryPassword,
    ) {}

    public function build(): self
    {
        $locale = $this->employee->company?->language;
        $locale = is_string($locale) && $locale !== '' ? $locale : 'fr';

        // S-5 (#1665) : les vues résolvent leurs chaînes via __() — il faut
        // épingler la locale applicative AVANT le rendu, sinon le corps du
        // mail se rend dans la locale ambiante du worker de queue.
        \Illuminate\Support\Facades\App::setLocale($locale);

        return $this
            ->subject(__('mail.welcome_employee.subject', ['name' => $this->employee->first_name]))
            ->markdown('emails.welcome-employee', [
                'employeeName' => $this->employee->full_name ?? $this->employee->first_name.' '.$this->employee->last_name,
                'loginUrl' => $this->loginUrl,
                'temporaryPassword' => $this->temporaryPassword,
                'companyName' => $this->employee->company?->name ?? 'Leopardo RH',
                'locale' => $locale,
            ]);
    }
}
