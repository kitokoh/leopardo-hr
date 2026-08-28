<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\DTOs\SendChannelMessageDTO;
use App\Modules\CRM\Domain\Exceptions\CrmChannelNotFoundException;
use App\Modules\CRM\Domain\Models\CrmChannel;
use App\Modules\CRM\Domain\Models\CrmChannelMessage;
use App\Modules\CRM\Infrastructure\Services\CrmChannelService;

/**
 * Cas d'usage : envoyer un message via un canal CRM configuré (issue #5725).
 *
 * Résout le canal dans le tenant courant (404 si absent/archivé), puis
 * délègue à CrmChannelService (consentement, quota, provider, dead-letter).
 */
final class SendChannelMessage
{
    public function __construct(private readonly CrmChannelService $service) {}

    public function execute(SendChannelMessageDTO $dto): CrmChannelMessage
    {
        $channel = CrmChannel::query()
            ->where('id', $dto->channelId)
            ->where('status', '!=', 'archived')
            ->first();

        if ($channel === null) {
            throw new CrmChannelNotFoundException();
        }

        return $this->service->send(
            $channel,
            $dto->toAddress,
            $dto->body,
            $dto->templateName,
            $dto->options,
        );
    }
}
