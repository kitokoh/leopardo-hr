<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Application\Actions\ActivateTravelAgencyAction;
use App\Modules\TravelAgency\Domain\Exceptions\TravelActivationFailedException;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

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

        // #7393 — la commande ne peut annoncer « activée » que si le flag a
        // réellement été persisté (vérifié par l'Action, relecture en base).
        // Sans cette garde, un échec d'écriture silencieux laissait la
        // verticale inaccessible (403 FEATURE_NOT_ENABLED) malgré le succès.
        try {
            $this->activateAction->execute($company);
        } catch (TravelActivationFailedException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

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
