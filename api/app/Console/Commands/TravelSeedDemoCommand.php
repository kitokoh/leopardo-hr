<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Application\Actions\ActivateTravelAgencyAction;
use App\Modules\TravelAgency\Infrastructure\Services\TravelDemoSeederService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * leopardo:travel:seed-demo — Seeds démonstratifs pour un tenant pilote.
 *
 * Usage :
 *   php artisan leopardo:travel:seed-demo {company}   # id UUID ou slug
 *
 * Active la verticale (flag + référentiel géo, idempotent) puis crée les
 * données de démonstration non sensibles (gares, bureaux — et à partir de
 * TRAVEL-204/207/209 : classes, routes, trajets, tarifs, réservation).
 *
 * Traçabilité : TRAVEL-107 (#6012).
 */
final class TravelSeedDemoCommand extends Command
{
    protected $signature = 'leopardo:travel:seed-demo
        {company : ID (UUID) ou slug de la company tenant}';

    protected $description = 'Seed de données de démonstration TravelAgency (idempotent).';

    public function __construct(
        private readonly ActivateTravelAgencyAction $activateAction,
        private readonly TravelDemoSeederService $demoSeeder,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $company = $this->resolveCompany((string) $this->argument('company'));

        if ($company === null) {
            $this->error("Company introuvable : {$this->argument('company')}");

            return self::FAILURE;
        }

        $this->activateAction->execute($company);
        $this->demoSeeder->seed($company);

        $this->info("Seed de démonstration TravelAgency terminé pour « {$company->name} ».");

        return self::SUCCESS;
    }

    /**
     * Résout le tenant par UUID **ou** par slug.
     *
     * Correctif audit 2026-09-14 : l'ancien test `str_contains($identifier, '-')`
     * prenait tout slug pour un UUID (tous les slugs contiennent un tiret) et
     * provoquait `SQLSTATE[22P02] invalid input syntax for type uuid` — la
     * commande, documentée « ID (UUID) ou slug », était inutilisable avec un
     * slug. Pattern aligné sur `SeedAccountingDemoCommand::resolveCompany()`
     * (`Str::isUuid` + repli slug).
     */
    private function resolveCompany(string $identifier): ?Company
    {
        if (Str::isUuid($identifier)) {
            /** @var Company|null $byId */
            $byId = Company::query()->where('id', $identifier)->first();

            if ($byId instanceof Company) {
                return $byId;
            }
        }

        /** @var Company|null $bySlug */
        $bySlug = Company::query()->where('slug', $identifier)->first();

        return $bySlug;
    }
}
