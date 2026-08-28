<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions\AutomationActions;

use App\Modules\CRM\Domain\Contracts\AutomationActionContract;
use App\Modules\CRM\Domain\Enums\CrmAutomationActionType;
use App\Modules\CRM\Domain\Enums\CrmChannelType;
use App\Modules\CRM\Domain\Models\CrmChannel;
use App\Modules\CRM\Infrastructure\Services\CrmChannelService;

/**
 * Action : envoyer un email via le canal configuré (issue #5728).
 *
 * Le canal email sera livré par #5726 (batch comm') ; tant qu'il n'existe
 * pas, l'action échoue proprement (run failed, jamais de 500 silencieux).
 */
final class SendEmailAction implements AutomationActionContract
{
    public function type(): string
    {
        return CrmAutomationActionType::SEND_EMAIL;
    }

    public function execute(array $config, array $context): void
    {
        $channel = CrmChannel::query()->where('type', CrmChannelType::EMAIL)->where('status', 'active')->first();
        if ($channel === null) {
            throw new \RuntimeException('Aucun canal email configure pour ce tenant (attendu avec #5726).');
        }

        $to = isset($config['to']) && is_string($config['to']) ? $config['to'] : ($context['email'] ?? null);
        if (! is_string($to) || $to === '') {
            throw new \RuntimeException('Destination email introuvable (config.to ou contexte).');
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
            'to' => $config['to'] ?? $context['email'] ?? null,
            'effect' => 'email envoye (simulation — aucun envoi reel)',
        ];
    }

    private function channelService(): CrmChannelService
    {
        // Résolution paresseuse : évite le cycle Actions → Service → Engine.
        return app(CrmChannelService::class);
    }
}
