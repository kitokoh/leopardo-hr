<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions\AutomationActions;

use App\Modules\CRM\Domain\Contracts\AutomationActionContract;
use App\Modules\CRM\Domain\Enums\CrmAutomationActionType;
use App\Modules\CRM\Domain\Enums\CrmChannelType;
use App\Modules\CRM\Domain\Models\CrmChannel;
use App\Modules\CRM\Infrastructure\Services\CrmChannelService;

/**
 * Action : envoyer un SMS via le canal configuré (issue #5728).
 */
final class SendSmsAction implements AutomationActionContract
{
    public function type(): string
    {
        return CrmAutomationActionType::SEND_SMS;
    }

    public function execute(array $config, array $context): void
    {
        $channel = CrmChannel::query()->where('type', CrmChannelType::SMS)->where('status', 'active')->first();
        if ($channel === null) {
            throw new \RuntimeException('Aucun canal SMS configure pour ce tenant.');
        }

        $to = $this->resolveTo($config, $context);
        if ($to === null) {
            throw new \RuntimeException('Destination SMS introuvable (config.to ou contexte).');
        }

        $body = isset($config['body']) && is_string($config['body']) ? $config['body'] : null;

        $this->channelService()->send($channel, $to, $body, null, [
            'contact_id' => $context['contact_id'] ?? null,
            'purpose' => $config['purpose'] ?? 'transactional',
        ]);
    }

    public function simulate(array $config, array $context): array
    {
        return [
            'action' => $this->type(),
            'to' => $this->resolveTo($config, $context),
            'effect' => 'SMS envoye (simulation — aucun envoi reel)',
        ];
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
