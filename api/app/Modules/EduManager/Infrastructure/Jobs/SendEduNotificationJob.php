<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Notification EduManager — EDU-014 (issue #5830).
 *
 * Envoie une notification transactionnelle (template `edu_*`) à une liste
 * d'employés du tenant (direction) via `CommunicationService::notifyEmployee`
 * (préférences, heures calmes, quotas, événement `communication_events`
 * tracé — le module Notification reste le hub transverse, aucun import
 * métier EduManager ici, isolation #5584).
 *
 * Tenant-scoped : `EnsureTenantContext` restaure search_path + current_company ;
 * un job sans compagnie résolvable est release(30), jamais échoué.
 * Idempotent par conception : chaque envoi est tracé dans
 * `communication_events` (un rejeu ne duplique pas l'envoi côté provider).
 */
class SendEduNotificationJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  list<int>  $employeeIds
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $companyId,
        public readonly array $employeeIds,
        public readonly string $templateKey,
        public readonly array $context = [],
    ) {
    }

    public function tenantCompanyId(): ?string
    {
        return $this->companyId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function handle(CommunicationService $communication): void
    {
        $company = Company::query()->withoutGlobalScopes()->find($this->companyId);

        if (! $company instanceof Company || $company->status !== 'active') {
            return;
        }

        foreach ($this->employeeIds as $employeeId) {
            $employee = \App\Core\Auth\Domain\Models\Employee::query()
                ->where('company_id', $company->id)
                ->where('id', $employeeId)
                ->first();

            if ($employee === null) {
                continue;
            }

            $communication->notifyEmployee($employee, $this->templateKey, $this->context);
        }
    }
}
