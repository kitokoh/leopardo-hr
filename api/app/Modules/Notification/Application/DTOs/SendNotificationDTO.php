<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\DTOs;

final class SendNotificationDTO
{
    public function __construct(
        public readonly int    $userId,
        public readonly string $type,
        public readonly string $title,
        public readonly ?string $body = null,
        /** @var array<string, mixed> */
        public readonly array  $data = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            type:   $data['type'],
            title:  $data['title'],
            body:   $data['body'] ?? null,
            data:   $data['data'] ?? [],
        );
    }
}
