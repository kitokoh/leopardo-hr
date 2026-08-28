<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Contracts\EmailProviderInterface;
use App\Modules\CRM\Domain\DTOs\EmailDeliveryResult;
use App\Modules\CRM\Domain\DTOs\EmailMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fournisseur email de repli — Issue #5726.
 *
 * N'envoie aucun email : journalise le message (sans PII superflue) et
 * retourne un message_id synthétique. Provider par défaut
 * (CRM_EMAIL_PROVIDER=log) — idéal pour la CI et les environnements de
 * test : le pipeline d'envoi, la suppression et l'audit sont exercés de
 * bout en bout sans dépendre d'un SMTP.
 */
final class LogEmailProvider implements EmailProviderInterface
{
    public function send(EmailMessage $message): EmailDeliveryResult
    {
        $messageId = (string) Str::uuid();

        Log::info('crm.email.sent', [
            'provider' => $this->providerName(),
            'message_id' => $messageId,
            'to' => $message->to,
            'subject' => $message->subject,
            'context' => $message->context,
        ]);

        return EmailDeliveryResult::sent($messageId);
    }

    public function providerName(): string
    {
        return 'log';
    }
}
