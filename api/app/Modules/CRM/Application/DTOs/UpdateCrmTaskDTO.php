<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\DTOs;

use Illuminate\Support\Carbon;

/**
 * Issue #5720 — Entrée de mise à jour de tâche CRM (champs optionnels).
 */
final class UpdateCrmTaskDTO
{
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?Carbon $dueAt = null,
        public readonly ?string $status = null,
        public readonly ?string $priority = null,
        public readonly ?int $assigneeId = null,
        public readonly ?int $accountId = null,
        public readonly ?int $contactId = null,
    ) {}
}
