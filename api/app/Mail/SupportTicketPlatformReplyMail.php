<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Mail\EmailTemplateResolver;
use App\Core\Mail\MailBrand;
use App\Modules\Platform\Domain\Models\PlatformSupportTicket;
use App\Support\I18nCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

/**
 * #7760 — l'équipe plateforme a répondu à un ticket support : notification
 * envoyée à l'employé auteur du ticket, dans SA langue (même résolution que
 * `UserInvitationMail` : préférence de l'employé, sinon langue du tenant).
 * Le corps ne recopie jamais la réponse : elle se lit dans l'espace client
 * (page Support).
 */
class SupportTicketPlatformReplyMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PlatformSupportTicket $ticket,
        public readonly Employee $recipient,
    ) {
        // Queued mail — le middleware SetLocale ne s'applique jamais ici :
        // la locale du destinataire est résolue explicitement.
        $this->locale = I18nCatalog::normalizeLocale(
            $recipient->preferred_language ?? $recipient->company?->language
        );
    }

    public function build(): self
    {
        App::setLocale($this->locale);

        $tpl = app(EmailTemplateResolver::class)->resolve('support_ticket_platform_reply', $this->locale, [
            ':ticket' => (string) $this->ticket->id,
            ':subject' => $this->ticket->subject,
            ':name' => $this->recipient->first_name ?? '',
            ':brand' => MailBrand::name(),
        ]);

        return $this
            ->subject($tpl->subject)
            ->view('emails.support-ticket-platform-reply', [
                'locale' => $this->locale,
                'tpl' => $tpl,
                'recipient' => $this->recipient,
            ]);
    }
}
