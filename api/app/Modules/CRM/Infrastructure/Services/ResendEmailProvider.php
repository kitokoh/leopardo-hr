<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Contracts\EmailProviderInterface;
use App\Modules\CRM\Domain\DTOs\EmailDeliveryResult;
use App\Modules\CRM\Domain\DTOs\EmailMessage;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fournisseur email Resend (production) — Issue #7752.
 *
 * ESP dédié pour l'envoi réel des campagnes et emails CRM :
 * POST https://api.resend.com/emails (clé `RESEND_KEY`, déjà déclarée dans
 * `config/services.php`). Sélectionné par `CRM_EMAIL_PROVIDER=resend`.
 *
 * Règles :
 *   - fail-closed : clé ou adresse expéditeur absente → résultat `failed`
 *     explicite, jamais d'exception ni d'envoi partiel ;
 *   - le `message_id` retourné par Resend devient le
 *     `provider_message_id` des campagnes (#5724) — corrélation webhook ;
 *   - toute exception transport est convertie en `failed` (même parti que
 *     `MailEmailProvider`, jamais de 500 silencieuse).
 */
final class ResendEmailProvider implements EmailProviderInterface
{
    private const ENDPOINT = 'https://api.resend.com/emails';

    public function send(EmailMessage $message): EmailDeliveryResult
    {
        $key = config('services.resend.key');
        $key = is_string($key) ? trim($key) : '';

        if ($key === '') {
            return EmailDeliveryResult::failed('RESEND_KEY absente — envoi refusé (fail-closed).');
        }

        $from = $this->fromAddress();

        if ($from === '') {
            return EmailDeliveryResult::failed('Adresse expéditeur absente (CRM_EMAIL_FROM / MAIL_FROM_ADDRESS).');
        }

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->asJson()
                ->post(self::ENDPOINT, [
                    'from' => $from,
                    'to' => [$message->to],
                    'subject' => $message->subject,
                    'text' => $message->body,
                ]);

            if (! $response->successful()) {
                $body = $response->json();
                $detail = is_array($body) && isset($body['message']) && is_string($body['message'])
                    ? $body['message']
                    : 'HTTP '.$response->status();

                return EmailDeliveryResult::failed('Resend: '.$detail);
            }

            $messageId = $response->json('id');

            if (! is_string($messageId) || $messageId === '') {
                return EmailDeliveryResult::failed('Resend: réponse sans identifiant de message.');
            }

            return EmailDeliveryResult::sent($messageId);
        } catch (Throwable $exception) {
            return EmailDeliveryResult::failed($exception->getMessage());
        }
    }

    public function providerName(): string
    {
        return 'resend';
    }

    private function fromAddress(): string
    {
        $from = config('crm.email.from_address');

        if (is_string($from) && trim($from) !== '') {
            return trim($from);
        }

        $mailFrom = config('mail.from.address');

        return is_string($mailFrom) ? trim($mailFrom) : '';
    }
}
