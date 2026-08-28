<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\DTOs;

use Illuminate\Support\Arr;

/**
 * Données d'entrée validées pour l'envoi d'un message de canal CRM.
 */
final class SendChannelMessageDTO
{
    /**
     * @param  array<string, mixed>  $options  contact_id, purpose, template_parameters…
     */
    public function __construct(
        public readonly string $channelId,
        public readonly string $toAddress,
        public readonly ?string $body,
        public readonly ?string $templateName,
        public readonly array $options = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $options = [
            'contact_id' => Arr::get($data, 'contact_id'),
            'purpose' => Arr::get($data, 'purpose', 'transactional'),
            'template_parameters' => Arr::get($data, 'template_parameters', []),
        ];

        return new self(
            channelId: (string) $data['channel_id'],
            toAddress: (string) $data['to'],
            body: isset($data['body']) && is_string($data['body']) ? $data['body'] : null,
            templateName: isset($data['template_name']) && is_string($data['template_name']) ? $data['template_name'] : null,
            options: $options,
        );
    }
}
