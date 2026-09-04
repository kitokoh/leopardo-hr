<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use Illuminate\Support\Facades\DB;

/**
 * Seed du référentiel RestaurantManager tenant-scoped (RESTO-105, issue #6162).
 *
 * Paramétrage de base d'un restaurant (sans données démo) :
 *   - une branche par défaut (code `MAIN`, « Branche principale ») ;
 *   - les unités de mesure (kg, l, u, pce) ;
 *   - les taux de TVA (TVA 0 % par défaut, TVA 19 %) ;
 *   - les catégories de produits (Entrées, Plats, Desserts, Boissons).
 *
 * Idempotence : insertOrIgnore sur les clés uniques tenant-scoped
 * (company_id, code) — rejouer le seed ne crée jamais de doublon et ne
 * réécrit jamais les modifications apportées par le tenant. Les catégories
 * (unique sur (company_id, branch_id, name) avec branch_id NULL, NULLs
 * multiples autorisés en Postgres) font l'objet d'une vérification
 * d'existence explicite avant insertion.
 */
final class RestaurantReferentialSeederService
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function seed(Company $company): void
    {
        $this->tenants->withinTenant($company, function () use ($company): void {
            $this->seedDefaultBranch($company);
            $this->seedUnits($company);
            $this->seedTaxRates($company);
            $this->seedCategories($company);
        });
    }

    private function seedDefaultBranch(Company $company): void
    {
        DB::table('restaurant_branches')->insertOrIgnore([
            'company_id' => $company->id,
            'code' => 'MAIN',
            'name' => 'Branche principale',
            'address' => null,
            'city' => $company->city ?: null,
            'phone' => null,
            'timezone' => $company->timezone ?: 'UTC',
            'currency' => $company->currency ?: 'DZD',
            'status' => RestaurantRecordStatus::ACTIVE->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedUnits(Company $company): void
    {
        $units = [
            ['code' => 'kg', 'label' => 'Kilogramme'],
            ['code' => 'l', 'label' => 'Litre'],
            ['code' => 'u', 'label' => 'Unite'],
            ['code' => 'pce', 'label' => 'Piece'],
        ];

        $rows = [];
        foreach ($units as $unit) {
            $rows[] = [
                'company_id' => $company->id,
                'code' => $unit['code'],
                'label' => $unit['label'],
                'status' => RestaurantRecordStatus::ACTIVE->value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('restaurant_units')->insertOrIgnore($rows);
    }

    private function seedTaxRates(Company $company): void
    {
        $rates = [
            ['code' => 'TVA0', 'label' => 'TVA 0%', 'rate_bps' => 0, 'is_default' => true],
            ['code' => 'TVA19', 'label' => 'TVA 19%', 'rate_bps' => 1900, 'is_default' => false],
        ];

        $rows = [];
        foreach ($rates as $rate) {
            $rows[] = [
                'company_id' => $company->id,
                'code' => $rate['code'],
                'label' => $rate['label'],
                'rate_bps' => $rate['rate_bps'],
                'is_default' => $rate['is_default'],
                'status' => RestaurantRecordStatus::ACTIVE->value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('restaurant_tax_rates')->insertOrIgnore($rows);
    }

    private function seedCategories(Company $company): void
    {
        foreach (['Entrées', 'Plats', 'Desserts', 'Boissons'] as $name) {
            $exists = DB::table('restaurant_categories')
                ->where('company_id', $company->id)
                ->whereNull('branch_id')
                ->where('name', $name)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('restaurant_categories')->insert([
                'company_id' => $company->id,
                'branch_id' => null,
                'name' => $name,
                'color' => null,
                'sort_order' => 0,
                'status' => RestaurantRecordStatus::ACTIVE->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
