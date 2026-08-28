<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions\AutomationActions;

use App\Modules\CRM\Domain\Contracts\AutomationActionContract;
use App\Modules\CRM\Domain\Enums\CrmAutomationActionType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Action : webhook HTTP sortant avec signature HMAC (issue #5728).
 *
 * Permet de déclencher un système externe (ERP, autre SaaS) sans coupler le
 * CRM. Le secret HMAC vient de la configuration de l'action (jamais loggé).
 * Timeout court + échec → run failed (jamais de retry infini).
 */
final class HttpWebhookAction implements AutomationActionContract
{
    public function type(): string
    {
        return CrmAutomationActionType::HTTP_WEBHOOK;
    }

    public function execute(array $config, array $context): void
    {
        $url = isset($config['url']) && is_string($config['url']) ? $config['url'] : '';
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL) || ! str_starts_with($url, 'https://')) {
            throw new \RuntimeException('URL de webhook sortant invalide (https requis).');
        }

        $secret = isset($config['secret']) && is_string($config['secret']) ? $config['secret'] : '';
        $payload = [
            'event' => $context['event'] ?? null,
            'entity_type' => $context['entity_type'] ?? null,
            'entity_id' => $context['entity_id'] ?? null,
            'data' => $context['data'] ?? [],
        ];

        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = $secret === '' ? '' : hash_hmac('sha256', $encoded, $secret);

        $response = Http::timeout(10)
            ->withHeaders($signature === '' ? [] : ['X-CRM-Signature' => 'sha256='.$signature])
            ->acceptJson()
            ->post($url, $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Webhook sortant HTTP '.$response->status());
        }

        Log::info('CRM automation: webhook sortant envoyé', [
            'url' => $url,
            'status' => $response->status(),
        ]);
    }

    public function simulate(array $config, array $context): array
    {
        return [
            'action' => $this->type(),
            'url' => $config['url'] ?? null,
            'effect' => 'webhook HTTP sortant appelé (simulation — aucun appel réel)',
        ];
    }
}
