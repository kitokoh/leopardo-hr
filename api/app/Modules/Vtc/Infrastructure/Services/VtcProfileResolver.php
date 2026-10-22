<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Shared\Contracts\Discovery\PublicProfileResolver;
use Illuminate\Database\Eloquent\Collection;

/**
 * C-1 (#8773) — Résolution d'un opérateur VTC public vers son tenant.
 *
 * Même source de vérité que {@see VtcDiscoverableProvider}
 * (`VtcMarketplaceService::eligibleOperators()`), jamais une requête
 * parallèle : un opérateur retiré de l'annuaire, dont la feature `vtc` a été
 * désactivée ou dont la société est `suspended`/`expired` disparaît donc de la
 * recherche ET du contact au même instant.
 *
 * Fail-closed : `null` si le slug n'est pas publiable — le contrôleur public
 * répond alors 404 uniforme, sans distinguer « inexistant » de « dépublié »
 * (pas d'énumération de slugs côté public).
 */
final class VtcProfileResolver implements PublicProfileResolver
{
    public function __construct(private readonly VtcMarketplaceService $marketplace) {}

    public function type(): string
    {
        return 'vtc_operator';
    }

    public function resolve(string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }

        /** @var Collection<int, Company> $operators */
        $operators = $this->marketplace->eligibleOperators();

        foreach ($operators as $company) {
            if ($company->slug !== $slug) {
                continue;
            }

            $companyId = $company->id;

            if ($companyId === '') {
                return null;
            }

            return [
                'company_id' => $companyId,
                'display_name' => $company->name,
            ];
        }

        return null;
    }
}
