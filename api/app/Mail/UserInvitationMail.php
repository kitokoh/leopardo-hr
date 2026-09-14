<?php

namespace App\Mail;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Support\I18nCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use App\Core\Mail\UsesEditableEmailTemplate;

class UserInvitationMail extends Mailable
{
    use UsesEditableEmailTemplate;

    use Queueable;
    use SerializesModels;

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

        $tpl = $this->editableEmailTemplate('user_invitation', $this->locale, [
            ':company' => $this->company->name,
            ':role' => $this->employee->manager_role ?? '',
            ':email' => $this->employee->email,
            ':brand' => \App\Core\Mail\MailBrand::name(),
        ]);

        return $this
            ->subject($tpl->subject)
            ->view('emails.user-invitation', ['locale' => $this->locale, 'tpl' => $tpl]);
    }
}

