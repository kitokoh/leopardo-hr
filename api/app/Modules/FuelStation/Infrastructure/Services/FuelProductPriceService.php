<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelProductPrice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Résolution et gestion des prix de vente — P0-1 (audit FuelStation 2026-10-16).
 *
 * Avant ce correctif, `POST .../fuel-station/sales` faisait confiance au
 * `unit_price` fourni par le client : le montant était bien calculé serveur,
 * mais à partir d'un prix arbitrable par l'appelant. Ce service fournit la
 * source de vérité serveur (`fuel_product_prices`).
 *
 * Sémantique de résolution :
 *  - `resolve()` renvoie le prix courant en unités mineures, ou `null` si le
 *    catalogue de prix est vide pour ce produit/unité ;
 *  - `null` est un résultat LÉGITIME et n'altère pas le comportement existant
 *    (rétro-compatibilité : tant qu'aucun prix n'est configuré, le prix reçu
 *    reste utilisé) — mais l'appelant compatible peut alors l'ignorer ou
 *    refuser la vente selon sa politique.
 */
final class FuelProductPriceService
{
    /**
     * Prix courant d'un produit, en unités mineures. `null` = aucun prix
     * configuré dans `fuel_product_prices` à cet instant.
     */
    public function resolve(string $companyId, string $productCode, string $unitCode = 'l', ?Carbon $at = null): ?int
    {
        if (! Schema::hasTable('fuel_product_prices')) {
            return null;
        }

        $at = $at ?? now();

        $price = FuelProductPrice::query()
            ->where('company_id', $companyId)
            ->where('product_code', $productCode)
            ->where('unit_code', $unitCode)
            ->where('status', FuelProductPrice::STATUS_ACTIVE)
            ->where('effective_from', '<=', $at)
            ->where(function ($query) use ($at): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>', $at);
            })
            ->orderByDesc('effective_from')
            ->first();

        if (! $price instanceof FuelProductPrice) {
            return null;
        }

        return (int) $price->unit_price_minor;
    }

    /**
     * Fixe un nouveau prix pour un produit (et clôt la période précédente).
     *
     * Aucune écriture silencieuse : l'ancien prix est conservé avec sa borne
     * `effective_to` — l'historique des changements de prix reste lisible.
     */
    public function setPrice(
        Employee $actor,
        string $productCode,
        int $unitPriceMinor,
        string $unitCode = 'l',
        ?Carbon $effectiveFrom = null,
    ): FuelProductPrice {
        $effectiveFrom = $effectiveFrom ?? now();

        // Clôt la période ouverte du même (produit, unité) sans l'effacer.
        FuelProductPrice::query()
            ->where('company_id', $actor->company_id)
            ->where('product_code', $productCode)
            ->where('unit_code', $unitCode)
            ->where('status', FuelProductPrice::STATUS_ACTIVE)
            ->whereNull('effective_to')
            ->where('effective_from', '<=', $effectiveFrom)
            ->update(['effective_to' => $effectiveFrom]);

        /** @var FuelProductPrice $price */
        $price = FuelProductPrice::query()->create([
            'company_id' => $actor->company_id,
            'product_code' => $productCode,
            'unit_code' => $unitCode,
            'unit_price_minor' => $unitPriceMinor,
            'effective_from' => $effectiveFrom,
            'status' => FuelProductPrice::STATUS_ACTIVE,
            'created_by' => $actor->id,
        ]);

        return $price;
    }

    /**
     * Historique des prix d'un produit (du plus récent au plus ancien).
     *
     * @return \Illuminate\Support\Collection<int, FuelProductPrice>
     */
    public function history(string $companyId, string $productCode, string $unitCode = 'l'): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('fuel_product_prices')) {
            return collect();
        }

        return FuelProductPrice::query()
            ->where('company_id', $companyId)
            ->where('product_code', $productCode)
            ->where('unit_code', $unitCode)
            ->orderByDesc('effective_from')
            ->get();
    }
}
