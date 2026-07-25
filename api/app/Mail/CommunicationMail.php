<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * PA2-COMM-007 - Generic transactional mailable used by
 * `MailMessageProvider` for every `CommunicationService::notifyEmployee()`
 * email dispatch (absences, payroll, security alerts, task comments,
 * announcements, ...). Content is already localized/rendered by
 * `CommunicationService` before this mailable is built, so this class only
 * carries the final subject/body plus an optional unsubscribe hint.
 */
class CommunicationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $bodyText,
        public readonly ?string $unsubscribeUrl = null,
    ) {}

    public function build(): self
    {
        return $this
            ->subject($this->subjectLine)
            ->view('emails.communication', [
                'bodyText' => $this->bodyText,
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ]);
    }
}
