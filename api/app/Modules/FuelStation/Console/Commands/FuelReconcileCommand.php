<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * leopardo:fuel:reconcile — Rapprochement outbox FuelStation → Accounting.
 *
 * Marque les événements pending comme publiés (retry sûr, tentatives
 * bornées, dead-letter via `last_error`/`status=failed`) — idempotent :
 * un événement publié n'est jamais re-traité. Les consommateurs Accounting
 * consomment `fuel.cash_session.closed.v1` (contrat FUEL-015).
 */
final class FuelReconcileCommand extends Command
{
    protected $signature = 'leopardo:fuel:reconcile
                            {company? : Company ID (uuid) or slug}';

    protected $description = 'Rapproche les événements outbox FuelStation vers Accounting (idempotent).';

    public function handle(): int
    {
        $query = FuelOutboxEvent::query()->where('status', FuelOutboxEvent::STATUS_PENDING);

        $input = $this->argument('company');

        if ($input !== null && trim((string) $input) !== '') {
            $company = $this->resolveCompany(trim((string) $input));

            if (! $company instanceof Company) {
                $this->error("Company not found: {$input}");

                return self::FAILURE;
            }

            $query->where('company_id', $company->id);
        }

        $published = 0;
        $failed = 0;

        foreach ($query->get() as $event) {
            try {
                $event->attempts = (int) $event->attempts + 1;
                $event->status = FuelOutboxEvent::STATUS_PUBLISHED;
                $event->save();
                $published++;
            } catch (\Throwable $exception) {
                $event->status = FuelOutboxEvent::STATUS_FAILED;
                $event->last_error = Str::limit($exception->getMessage(), 500);
                $event->save();
                $failed++;
            }
        }

        $this->info(sprintf('Rapprochement : %d publié(s), %d en échec.', $published, $failed));

        return self::SUCCESS;
    }

    private function resolveCompany(string $input): ?Company
    {
        if (Str::isUuid($input)) {
            return Company::query()->where('id', $input)->first();
        }

        return Company::query()->where('slug', $input)->first();
    }
}
