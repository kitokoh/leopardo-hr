<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\DTOs;

use Illuminate\Support\Carbon;

/**
 * Issue #5720 — Entrée de création de tâche CRM (validation amont : FormRequest).
 */
final class CreateCrmTaskDTO
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly ?Carbon $dueAt = null,
        public readonly string $priority = 'medium',
        public readonly ?int $assigneeId = null,
        public readonly ?int $accountId = null,
        public readonly ?int $contactId = null,
    ) {}
}
