<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Integrations\WhatsApp;

use App\Modules\CRM\Domain\Exceptions\CrmChannelNotConfiguredException;
use App\Modules\CRM\Domain\Exceptions\CrmProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Client officiel WhatsApp Business Cloud API (Meta Graph API, issue #5725).
 *
 * - Token d'accès : config `services.whatsapp.token` (env
 *   WHATSAPP_CLOUD_API_TOKEN) — secret manager, JAMAIS en frontend/DB/logs.
 * - Fail-closed : sans token configuré, toute tentative lève
 *   CrmChannelNotConfiguredException (503).
 * - 429 / 5xx / timeout → CrmProviderException retryable (dead-letter après
 *   épuisement des tentatives) ; 400 métier → CrmProviderException non
 *   retryable.
 * - Le corps des requêtes n'est jamais journalisé (PII).
 */
final class WhatsAppCloudApiClient
{
    private const GRAPH_VERSION = 'v20.0';

    private const MAX_TIMEOUT_SECONDS = 15;

    public function sendText(string $phoneNumberId, string $to, string $body, string $accessToken): string
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $body],
        ];

        $response = $this->request('POST', "/{$phoneNumberId}/messages", $payload, $accessToken);
        $data = $response->json();

        return is_string($data['messages'][0]['id'] ?? null) ? $data['messages'][0]['id'] : '';
    }

    public function sendTemplate(string $phoneNumberId, string $to, string $templateName, string $languageCode, array $parameters, string $accessToken): string
    {
        $components = [];
        if ($parameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(
                    static fn (string $value): array => ['type' => 'text', 'text' => $value],
                    $parameters,
                ),
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
            ],
        ];

        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        $response = $this->request('POST', "/{$phoneNumberId}/messages", $payload, $accessToken);
        $data = $response->json();

        return is_string($data['messages'][0]['id'] ?? null) ? $data['messages'][0]['id'] : '';
    }

    /**
     * Résout le numéro de téléphone associé à un phone_number_id (utilisé
     * par le webhook pour retrouver le tenant propriétaire).
     */
    public function resolvePhoneNumberId(string $phoneNumberId, string $accessToken): ?string
    {
        try {
            $response = $this->request('GET', "/{$phoneNumberId}", [], $accessToken);

            return $response->json('display_phone_number');
        } catch (CrmProviderException $e) {
            Log::warning('CRM WhatsApp: phone_number_id introuvable (fail-closed)', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(string $method, string $path, array $payload, string $accessToken): Response
    {
        if ($accessToken === '') {
            throw new CrmChannelNotConfiguredException();
        }

        $url = 'https://graph.facebook.com/'.self::GRAPH_VERSION.$path;

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(self::MAX_TIMEOUT_SECONDS)
                ->retry(1, 200, throw: false)
                ->send($method, $url, $method === 'GET' ? ['query' => $payload] : ['json' => $payload]);
        } catch (ConnectionException|RequestException $e) {
            throw new CrmProviderException('WhatsApp provider unreachable: '.$e->getMessage());
        } catch (Throwable $e) {
            throw new CrmProviderException('WhatsApp provider error: '.$e->getMessage());
        }

        if ($response->successful()) {
            return $response;
        }

        $status = $response->status();
        $body = is_array($response->json()) ? $response->json() : [];

        // Les erreurs 429/5xx sont retryables ; les 4xx métier non.
        $retryable = $status === 429 || $status >= 500;

        Log::warning('CRM WhatsApp: provider error (fail-closed, pas de retry infini)', [
            'status' => $status,
            'retryable' => $retryable,
            'error_code' => $body['error']['code'] ?? 'unknown',
        ]);

        throw new CrmProviderException(
            'WhatsApp provider returned HTTP '.$status,
            $retryable,
            (string) ($body['error']['code'] ?? 'PROVIDER_HTTP_'.$status),
        );
    }
}
