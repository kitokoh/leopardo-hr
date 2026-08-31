<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions\AutomationActions;

use App\Modules\CRM\Domain\Contracts\AutomationActionContract;
use App\Modules\CRM\Domain\Enums\CrmAutomationActionType;
use App\Modules\CRM\Domain\Enums\CrmChannelType;
use App\Modules\CRM\Domain\Models\CrmChannel;
use App\Modules\CRM\Infrastructure\Services\CrmChannelService;
use Illuminate\Support\Facades\Log;

/**
 * Action : envoyer un message WhatsApp via le canal configuré (issue #5728).
 */
final class SendWhatsAppAction implements AutomationActionContract
{
    public function type(): string
    {
        return CrmAutomationActionType::SEND_WHATSAPP;
    }

    public function execute(array $config, array $context): void
    {
        $channel = $this->channelOrNull(CrmChannelType::WHATSAPP);
        if ($channel === null) {
            throw new \RuntimeException('Aucun canal WhatsApp configure pour ce tenant.');
        }

        $to = $this->resolveTo($config, $context);
        if ($to === null) {
            throw new \RuntimeException('Destination WhatsApp introuvable (config.to ou contexte).');
        }

        $body = isset($config['body']) && is_string($config['body']) ? $config['body'] : null;
        $template = isset($config['template_name']) && is_string($config['template_name']) ? $config['template_name'] : null;

        $this->channelService()->send($channel, $to, $body, $template, [
            'contact_id' => $context['contact_id'] ?? null,
            'purpose' => $config['purpose'] ?? 'transactional',
        ]);
    }

    public function simulate(array $config, array $context): array
    {
        return [
            'action' => $this->type(),
            'to' => $this->resolveTo($config, $context),
            'template_name' => $config['template_name'] ?? null,
            'effect' => 'message WhatsApp envoye (simulation — aucun envoi reel)',
        ];
    }

    private function channelOrNull(string $type): ?CrmChannel
    {
        return CrmChannel::query()->where('type', $type)->where('status', 'active')->first();
    }

    private function channelService(): CrmChannelService
    {
        // Résolution paresseuse : évite le cycle Actions → Service → Engine.
        return app(CrmChannelService::class);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    private function resolveTo(array $config, array $context): ?string
    {
        if (isset($config['to']) && is_string($config['to'])) {
            return $config['to'];
        }

        $to = $context['to'] ?? $context['phone'] ?? null;

        return is_string($to) ? $to : null;
    }
}
