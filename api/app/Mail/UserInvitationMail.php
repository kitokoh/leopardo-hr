<?php

namespace App\Mail;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Support\I18nCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class UserInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public readonly string $locale;

    public function __construct(
        public readonly Company $company,
        public readonly Employee $employee,
        public readonly string $activationUrl,
        public readonly string $invitedByEmail,
    ) {
        // Sent via Mail::to()->send() from services/controllers that may run
        // outside the SetLocale middleware (e.g. queued dispatch, console
        // commands, platform-admin provisioning). Resolve explicitly so the
        // invitee sees the invitation in their own preferred language.
        $this->locale = I18nCatalog::normalizeLocale(
            $employee->preferred_language ?? $company->language
        );
    }

    public function build(): self
    {
        App::setLocale($this->locale);

        return $this
            ->subject(__('emails.user_invitation_subject'))
            ->view('emails.user-invitation', ['locale' => $this->locale]);
    }
}

