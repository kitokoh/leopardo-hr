<?php

namespace App\Mail;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly Employee $employee,
        public readonly string $activationUrl,
        public readonly string $invitedByEmail,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Bienvenue sur Leopardo RH - Activez votre compte')
            ->view('emails.user-invitation');
    }
}

