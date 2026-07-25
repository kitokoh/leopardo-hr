<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\DTOs;

final readonly class CreateThreadDTO
{
    public function __construct(
        public string $title,
        public string $body,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
        public ?int $recipientId = null,
    ) {}
}
