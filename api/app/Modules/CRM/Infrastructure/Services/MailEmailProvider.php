<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Mail\CommunicationMail;
use App\Modules\CRM\Domain\Contracts\EmailProviderInterface;
use App\Modules\CRM\Domain\DTOs\EmailDeliveryResult;
use App\Modules\CRM\Domain\DTOs\EmailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fournisseur email via Laravel Mail — Issue #5726.
 *
 * Utilise le mailable générique `CommunicationMail` (déjà localisé par
 * l'appelant) et le mailer configuré par `MAIL_MAILER` (smtp/log/…).
 * Une exception du transport est convertie en résultat `failed` — jamais
 * d'erreur 500 silencieuse.
 */
final class MailEmailProvider implements EmailProviderInterface
{
    public function send(EmailMessage $message): EmailDeliveryResult
    {
        try {
            $messageId = (string) Str::uuid();

            Mail::to($message->to)->send(
                new CommunicationMail($message->subject, $message->body),
            );

            return EmailDeliveryResult::sent($messageId);
        } catch (Throwable $exception) {
            return EmailDeliveryResult::failed($exception->getMessage());
        }
    }

    public function providerName(): string
    {
        return 'mail';
    }
}
