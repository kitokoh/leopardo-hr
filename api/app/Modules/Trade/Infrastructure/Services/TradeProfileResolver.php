<?php

declare(strict_types=1);

namespace App\Modules\Trade\Infrastructure\Services;

use App\Shared\Contracts\Discovery\PublicProfileResolver;
use Illuminate\Support\Facades\DB;

/**
 * P2-01 (#8802) — résolution d'une entreprise commerciale PUBLIQUE vers son
 * tenant.
 *
 * Miroir de {@see \App\Modules\RestaurantManager\Infrastructure\Services\RestaurantProfileResolver} :
 * la résolution est **cross-tenant** (le slug public est unique GLOBAL, porté
 * par `trade_company_profiles_public_slug_unique`), donc elle ne peut pas
 * passer par le scope `BelongsToCompany` du modèle — elle lit explicitement le
 * schéma partagé puis le tenant qui POSSÈDE le profil.
 *
 * **Fail-closed.** Quatre conditions cumulatives, chacune indispensable :
 *
 *  1. `public_slug = {slug}` — sans index unique, deux tenants pourraient
 *     revendiquer la même fiche ;
 *  2. `is_public = true` — l'opt-in explicite (défaut `false`) ;
 *  3. `public_name` non vide — un profil opt-in mais sans nom publiable n'est
 *     pas une fiche ;
 *  4. la feature `trade` est active chez la société, et la société n'est ni
 *     `suspended` ni `expired`.
 *
 * Toute condition manquante renvoie `null`. Le contrôleur public traduit ce
 * `null` en **404 uniforme** : un visiteur ne peut pas distinguer « slug
 * inexistant » de « profil dépublié », ce qui empêche l'énumération des
 * professionnels inscrits.
 */
final class TradeProfileResolver implements PublicProfileResolver
{
    public function type(): string
    {
        return 'trade_company';
    }

    public function resolve(string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }

        $table = $this->isPostgres() ? 'shared_tenants.trade_company_profiles' : 'trade_company_profiles';

        $row = DB::table($table.' as p')
            ->join('companies as c', 'c.id', '=', 'p.company_id')
            ->where('p.public_slug', $slug)
            ->where('p.is_public', true)
            ->whereNotNull('p.public_name')
            ->where('p.public_name', '!=', '')
            // L'état commercial de la société s'applique sur TOUS les drivers :
            // une société radiée ne doit pas rester joignable, même en test.
            ->whereNotIn('c.status', ['suspended', 'expired'])
            ->when($this->isPostgres(), function ($query): void {
                // Feature active : colonne JSON, donc syntaxe PostgreSQL. Sur un
                // autre driver le test d'activation n'est pas exprimable ici ;
                // `is_public` reste la barrière fail-closed.
                $query->whereRaw("coalesce((c.features ->> 'trade')::boolean, false) is true");
            })
            ->select(['p.company_id as company_id', 'p.public_name as public_name'])
            ->first();

        if ($row === null) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = (array) $row;

        $companyId = $data['company_id'] ?? null;

        if (! is_string($companyId) || $companyId === '') {
            return null;
        }

        $name = $data['public_name'] ?? null;

        return [
            'company_id' => $companyId,
            'display_name' => is_string($name) ? $name : '',
        ];
    }

    private function isPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }
}
