<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Infrastructure\Services\FuelAnomalyService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * leopardo:fuel:anomalies — Détection des anomalies FuelStation (outbox).
 *
 * Usage :
 *   php artisan leopardo:fuel:anomalies              # toutes les company actives
 *   php artisan leopardo:fuel:anomalies {company}    # une company (uuid ou slug)
 *
 * Idempotent (FUEL-019) : une alerte par entité/période (dédup outbox).
 */
final class FuelAnomaliesCommand extends Command
{
    protected $signature = 'leopardo:fuel:anomalies
                            {company? : Company ID (uuid) or slug}';

    protected $description = 'Publie les anomalies FuelStation (compteur, clôture manquante, écart) dans l\'outbox.';

    public function __construct(private readonly FuelAnomalyService $anomalies)
    {
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
                ->filter(fn (Company $company) => $company->hasFeature('fuel_station'))
                ->values()
                ->all();
        }

        $total = ['published' => 0, 'duplicates' => 0];

        foreach ($companies as $company) {
            $result = $this->anomalies->scanCompany($company);
            $total['published'] += $result['anomalies_published'];
            $total['duplicates'] += $result['duplicates'];

            $this->info(sprintf(
                '%s (%s) : %d anomalie(s) publiée(s), %d doublon(s).',
                $company->name,
                $company->slug,
                $result['anomalies_published'],
                $result['duplicates'],
            ));
        }

        $this->info(sprintf('Total : %d publiée(s), %d doublon(s).', $total['published'], $total['duplicates']));

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
