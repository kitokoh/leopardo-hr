<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Infrastructure\Context;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\App;
use RuntimeException;

/**
 * Farm OS — Résolution de l'entreprise courante côté Infrastructure.
 *
 * Les contrôleurs Farm OS ne doivent jamais fabriquer eux-mêmes le contexte de
 * tenant : ce point unique évite qu'un écran agricole finisse par interroger la
 * mauvaise entreprise (fuite inter-tenant sur des données de parcelles, de
 * cheptel ou de récoltes). Le contexte est résolu par TenantManager via le
 * helper `currentCompany()` (voir Core/Tenant), jamais par un `where` recopié.
 *
 * Fail-closed : si aucun contexte n'est établi (middleware `tenant` traversé à
 * tort), on lève — le contrôleur renvoie une erreur plutôt que des lignes
 * d'une autre entreprise.
 */
final class FarmCompanyContext
{
    public function current(): Company
    {
        $company = App::bound('current_company') ? currentCompany() : null;

        if (! $company instanceof Company) {
            throw new RuntimeException('No tenant context is established for this Farm OS request.');
        }

        return $company;
    }

    public function currentId(): string
    {
        return (string) $this->current()->id;
    }
}
