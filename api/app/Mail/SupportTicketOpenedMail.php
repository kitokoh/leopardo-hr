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
 * #7760 — un client vient d'ouvrir un ticket support : notification envoyée
 * à chaque super-admin porteur de la permission plateforme `support.manage`
 * (équipe support). Le corps ne recopie jamais le message du ticket : la
 * conversation se lit dans la console plateforme.
 */
class SupportTicketOpenedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PlatformSupportTicket $ticket,
        public readonly Company $company,
    ) {
        // Destinataires internes (équipe plateforme) : la langue de travail de
        // la console est celle de l'application, pas celle du tenant.
        $this->locale = I18nCatalog::normalizeLocale((string) config('app.locale'));
    }

    public function build(): self
    {
        App::setLocale($this->locale);

        $tpl = app(EmailTemplateResolver::class)->resolve('support_ticket_opened', $this->locale, [
            ':ticket' => (string) $this->ticket->id,
            ':company' => $this->company->name,
            ':subject' => $this->ticket->subject,
            ':category' => $this->ticket->category,
            ':priority' => $this->ticket->priority,
            ':brand' => MailBrand::name(),
        ]);

        return $this
            ->subject($tpl->subject)
            ->view('emails.support-ticket-opened', ['locale' => $this->locale, 'tpl' => $tpl]);
    }
}
