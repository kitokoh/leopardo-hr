<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
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

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.welcome_employee.subject', ['name' => $this->employee->first_name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.welcome-employee',
            with: [
                'employeeName' => $this->employee->full_name ?? $this->employee->first_name.' '.$this->employee->last_name,
                'loginUrl' => $this->loginUrl,
                'temporaryPassword' => $this->temporaryPassword,
                'companyName' => $this->employee->company?->name ?? 'Leopardo RH',
            ],
        );
    }
}
