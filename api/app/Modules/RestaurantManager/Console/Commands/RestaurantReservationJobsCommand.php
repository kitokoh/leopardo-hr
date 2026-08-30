<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantReservationJobsService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * leopardo:restaurant:reservation-jobs — No-show + rappels de réservation.
 *
 * Usage :
 *   php artisan leopardo:restaurant:reservation-jobs              # toutes les company actives
 *   php artisan leopardo:restaurant:reservation-jobs {company}    # une company
 *
 * Idempotent (RESTO-608) : passage no-show unique par réservation, rappel
 * outbox `restaurant.reservation.reminder.v1` dédupliqué par (réservation, jour).
 */
final class RestaurantReservationJobsCommand extends Command
{
    protected $signature = 'leopardo:restaurant:reservation-jobs
                            {company? : Company ID (uuid) or slug}';

    protected $description = 'No-show + rappels de réservation RestaurantManager (idempotent).';

    public function __construct(
        private readonly RestaurantReservationJobsService $jobs,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $input = $this->argument('company');
        $companies = [];

        if ($input !== null && trim((string) $input) !== '') {
            $company = $this->resolveCompany(trim((string) $input));

            if (! $company instanceof Company) {
                $this->error("Company not found: {$input}");

                return self::FAILURE;
            }

            $companies = [$company];
        } else {
            $companies = Company::query()
                ->get()
                ->filter(fn (Company $company) => $company->hasFeature('restaurantmanager'))
                ->values()
                ->all();
        }

        $totals = ['no_shows' => 0, 'reminders_created' => 0, 'reminders_duplicates' => 0];

        foreach ($companies as $company) {
            $result = $this->jobs->run($company);
            $totals['no_shows'] += $result['no_shows'];
            $totals['reminders_created'] += $result['reminders_created'];
            $totals['reminders_duplicates'] += $result['reminders_duplicates'];

            $this->info(sprintf(
                '%s (%s) : %d no-show(s), %d rappel(s), %d doublon(s).',
                $company->name,
                $company->slug,
                $result['no_shows'],
                $result['reminders_created'],
                $result['reminders_duplicates'],
            ));
        }

        $this->info(sprintf(
            'Total : %d no-show(s), %d rappel(s), %d doublon(s).',
            $totals['no_shows'],
            $totals['reminders_created'],
            $totals['reminders_duplicates'],
        ));

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
