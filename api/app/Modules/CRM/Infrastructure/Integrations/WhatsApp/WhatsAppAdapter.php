<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Integrations\WhatsApp;

use App\Modules\CRM\Domain\Contracts\ChannelAdapterContract;
use App\Modules\CRM\Domain\Enums\CrmChannelType;
use App\Modules\CRM\Domain\Exceptions\CrmProviderException;
use App\Modules\CRM\Infrastructure\Services\CrmPhoneNormalizer;
use Illuminate\Support\Facades\Log;

/**
 * Adaptateur WhatsApp Business Cloud API (issue #5725).
 *
 * Fournit le contrat commun ChannelAdapterContract (send/verify/normalize/
 * revoke) sur le client Graph API officiel. Le numéro de téléphone WhatsApp
 * du tenant et la langue des templates viennent de la configuration non
 * sensible du canal (settings), le token du secret manager (env).
 */
final class WhatsAppAdapter implements ChannelAdapterContract
{
    public function __construct(
        private readonly WhatsAppCloudApiClient $client,
        private readonly CrmPhoneNormalizer $normalizer,
    ) {}

    public function send(string $toAddress, ?string $body, ?string $templateName, array $settings): array
    {
        $phoneNumberId = $this->phoneNumberId($settings);
        $token = $this->accessToken();
        $language = is_string($settings['language_code'] ?? null) ? (string) $settings['language_code'] : 'fr';

        if ($templateName !== null && $templateName !== '') {
            $parameters = is_array($settings['template_parameters'] ?? null)
                ? array_map(static fn (mixed $v): string => (string) $v, $settings['template_parameters'])
                : [];
            $providerMessageId = $this->client->sendTemplate($phoneNumberId, $toAddress, $templateName, $language, $parameters, $token);
        } elseif ($body !== null && $body !== '') {
            $providerMessageId = $this->client->sendText($phoneNumberId, $toAddress, $body, $token);
        } else {
            throw new CrmProviderException('WhatsApp: body ou template requis', false, 'EMPTY_PAYLOAD');
        }

        // Le provider ne renvoie pas de coût au moment de l'envoi : la clé
        // `cost` est omise (contrat `cost?: float`) — l'observabilité des
        // coûts est assurée par l'audit.
        return [
            'provider_message_id' => $providerMessageId,
            'status' => 'sent',
        ];
    }

    public function verify(string $address, array $settings): bool
    {
        return $this->normalizer->normalizePhone($address) !== null;
    }

    public function normalize(string $address): ?string
    {
        return $this->normalizer->normalizePhone($address);
    }

    public function revoke(string $providerMessageId, array $settings): bool
    {
        // La Cloud API n'expose pas de révocation de message déjà envoyé
        // (la fenêtre de service s'applique). Best-effort = ack, la vraie
        // contrainte est le consentement + templates approuvés en amont.
        Log::info('CRM WhatsApp: revoke non supporté par la Cloud API (best-effort ack)', [
            'provider_message_id' => $providerMessageId,
        ]);

        return true;
    }

    public function channelType(): string
    {
        return CrmChannelType::WHATSAPP;
    }

    private function phoneNumberId(array $settings): string
    {
        $phoneNumberId = $settings['phone_number_id'] ?? null;
        if (! is_string($phoneNumberId) || $phoneNumberId === '') {
            throw new CrmProviderException('WhatsApp: phone_number_id manquant dans la configuration du canal', false, 'MISSING_PHONE_NUMBER_ID');
        }

        return $phoneNumberId;
    }

    private function accessToken(): string
    {
        return (string) config('services.whatsapp.token', '');
    }
}
