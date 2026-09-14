<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Application\Actions\ActivateTravelAgencyAction;
use Illuminate\Console\Command;

/**
 * leopardo:travel:activate — Active la verticale TravelAgency pour un tenant.
 *
 * Usage :
 *   php artisan leopardo:travel:activate {company}   # id UUID ou slug
 *
 * Effets (idempotents) :
 *   - feature flag `travelagency` activé (companies.features) ;
 *   - référentiel géographique seedé (pays + villes, insertOrIgnore).
 *
 * Traçabilité : TRAVEL-105 (#6010) — activation par feature flag seul ;
 * le branchement sur l'orchestrateur de provisioning viendra avec PLAT-001.
 */
final class TravelActivateCommand extends Command
{
    protected $signature = 'leopardo:travel:activate
        {company : ID (UUID) ou slug de la company tenant}';

    protected $description = 'Active la verticale TravelAgency pour un tenant (flag + seed géographique).';

    public function __construct(private readonly ActivateTravelAgencyAction $activateAction)
    {
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

        // #7393 : la commande annonçait « activée » sans qu'aucune écriture
        // n'ait eu lieu. On relit désormais l'état réel depuis la base avant
        // d'affirmer quoi que ce soit — un échec silencieux coûte des heures
        // de diagnostic (403 FEATURE_NOT_ENABLED sur toute la verticale).
        $persisted = Company::query()
            ->whereKey($company->getKey())
            ->first();

        if ($persisted === null || ! $persisted->hasFeature('travelagency')) {
            $this->error(
                "Échec : le flag « travelagency » n'a pas été persisté pour {$company->id} "
                .'(companies.features.travelagency est resté absent).'
            );

            return self::FAILURE;
        }

        $this->info("Verticale TravelAgency activée pour « {$company->name} » ({$company->id}).");

        return self::SUCCESS;
    }

    private function resolveCompany(string $identifier): ?Company
    {
        if (str_contains($identifier, '-')) {
            return Company::query()->where('id', $identifier)->first();
        }

        return Company::query()->where('slug', $identifier)->first();
    }
}
