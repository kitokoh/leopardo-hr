<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Application\DTOs;

use Carbon\Carbon;

final class CreateSocialPostDTO
{
    /**
     * @param  array<int, string>  $targetPlatforms
     * @param  array<int, string>|null  $mediaPaths
     */
    public function __construct(
        public readonly string $companyId,
        public readonly ?int $createdBy,
        public readonly string $content,
        public readonly array $targetPlatforms,
        public readonly ?array $mediaPaths = null,
        public readonly ?Carbon $scheduledAt = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            companyId: (string) $data['company_id'],
            createdBy: isset($data['created_by']) ? (int) $data['created_by'] : null,
            content: (string) $data['content'],
            targetPlatforms: array_values((array) $data['target_platforms']),
            mediaPaths: isset($data['media_paths']) ? array_values((array) $data['media_paths']) : null,
            scheduledAt: isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null,
        );
    }
}
