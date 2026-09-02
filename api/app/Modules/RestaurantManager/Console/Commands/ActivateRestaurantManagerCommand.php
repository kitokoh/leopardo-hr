<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Application\Actions\ActivateRestaurantManagerAction;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * leopardo:restaurant:activate — Active la verticale RestaurantManager pour
 * un tenant (RESTO-105, issue #6162).
 *
 * Usage :
 *   php artisan leopardo:restaurant:activate {company}   # id UUID ou slug
 *
 * Effets (idempotents) :
 *   - feature flag `restaurantmanager` activé (companies.features) ;
 *   - référentiel seedé (branche par défaut, unités, TVA, catégories).
 *
 * Traçabilité : RESTO-105 (#6162) — activation par feature flag seul ; le
 * branchement sur l'orchestrateur de provisioning viendra avec PLAT-001.
 */
final class ActivateRestaurantManagerCommand extends Command
{
    protected $signature = 'leopardo:restaurant:activate
                            {company : ID (uuid) or slug of the target company}';

    protected $description = 'Activates the RestaurantManager vertical for a tenant (feature flag + referential seed).';

    public function __construct(private readonly ActivateRestaurantManagerAction $activateAction)
    {
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

        $this->info(sprintf('RestaurantManager activated for %s (%s).', $company->name, $company->slug));

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
