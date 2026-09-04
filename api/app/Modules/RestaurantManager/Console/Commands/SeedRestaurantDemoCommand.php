<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Application\Actions\ActivateRestaurantManagerAction;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantDemoSeederService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * leopardo:restaurant:seed-demo — Seed de données de démonstration
 * RestaurantManager (RESTO-107, issue #6164).
 *
 * Usage :
 *   php artisan leopardo:restaurant:seed-demo {company}   # id UUID ou slug
 *
 * Active la verticale (flag + référentiel, idempotent) puis crée la vitrine
 * de démonstration non sensible (branche DEMO, zones, tables, produits,
 * ingrédients + stocks, menu, session de caisse fermée avec commande
 * soldée). Rejouable sans doublon.
 */
final class SeedRestaurantDemoCommand extends Command
{
    protected $signature = 'leopardo:restaurant:seed-demo
                            {company : ID (uuid) or slug of the target company}';

    protected $description = 'Seeds RestaurantManager demo data for a tenant (idempotent).';

    public function __construct(
        private readonly ActivateRestaurantManagerAction $activateAction,
        private readonly RestaurantDemoSeederService $demoSeeder,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $input = $this->argument('company');

        if (trim($input) === '') {
            $this->error('Company argument is required: ID (uuid) or slug (e.g. techcorp-algerie).');

            return self::FAILURE;
        }

        $company = $this->resolveCompany(trim($input));

        if (! $company instanceof Company) {
            $this->error("Company not found: {$input}");

            return self::FAILURE;
        }

        $this->activateAction->execute($company);
        $this->demoSeeder->seed($company);

        $this->info(sprintf('Restaurant demo data seeded for %s (%s).', $company->name, $company->slug));

        return self::SUCCESS;
    }

    private function resolveCompany(string $input): ?Company
    {
        if (Str::isUuid($input)) {
            /** @var Company|null $byId */
            $byId = Company::query()->where('id', $input)->first();

            if ($byId instanceof Company) {
                return $byId;
            }
        }

        /** @var Company|null $bySlug */
        $bySlug = Company::query()->where('slug', $input)->first();

        return $bySlug;
    }
}
