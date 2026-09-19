<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Mail\EmailTemplateResolver;
use App\Core\Mail\MailBrand;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Platform\Domain\Models\PlatformSupportTicket;
use App\Support\I18nCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

/**
 * #7760 — un client a répondu sur un ticket support : notification envoyée
 * au super-admin assigné (`assigned_super_admin_id`), sinon à l'équipe
 * `support.manage`. Le corps ne recopie jamais la réponse : elle se lit
 * dans la console plateforme.
 */
class SupportTicketTenantReplyMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PlatformSupportTicket $ticket,
        public readonly Company $company,
    ) {
        // Destinataires internes (équipe plateforme) : langue de l'application.
        $this->locale = I18nCatalog::normalizeLocale((string) config('app.locale'));
    }

    public function build(): self
    {
        App::setLocale($this->locale);

        $tpl = app(EmailTemplateResolver::class)->resolve('support_ticket_tenant_reply', $this->locale, [
            ':ticket' => (string) $this->ticket->id,
            ':company' => $this->company->name,
            ':subject' => $this->ticket->subject,
            ':brand' => MailBrand::name(),
        ]);

        return $this
            ->subject($tpl->subject)
            ->view('emails.support-ticket-tenant-reply', ['locale' => $this->locale, 'tpl' => $tpl]);
    }
}
