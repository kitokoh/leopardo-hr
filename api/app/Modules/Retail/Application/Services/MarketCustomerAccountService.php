<?php

declare(strict_types=1);

namespace App\Modules\Retail\Application\Services;

use App\Modules\Retail\Domain\Enums\RetailOrderSource;
use App\Modules\Retail\Domain\Models\MarketCustomerAccount;
use App\Modules\Retail\Domain\Models\RetailOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Issue #7814 — Comptes acheteurs grand public de Leopardo Marché
 * (BC-17 RETAIL, backlog post-v1 spec MARKETPLACE_RETAIL_PUBLIC.md §6).
 *
 * Rattachement des commandes en ligne et « mes commandes » CROSS-BOUTIQUES,
 * strictement ISOLÉES À L'ACHETEUR : chaque requête est bornée par
 * `customer_account_id` (jamais de lecture non bornée), le scope tenant est
 * levé explicitement (`withoutGlobalScope('company')`) car un acheteur
 * n'appartient à aucun tenant — même pattern que
 * TravelCustomerAccountService (#7739) et RetailMarketplaceService (#7807).
 */
final class MarketCustomerAccountService
{
    /**
     * Rattache au compte les commandes EN LIGNE existantes dont l'e-mail
     * client correspond à l'e-mail du compte (rattachement « à la création »
     * du compte, critère #7814 — la vérification par lien e-mail est un lot
     * ultérieur). Seules les commandes encore orphelines
     * (customer_account_id NULL) sont revendiquées : un compte ne vole
     * jamais la commande d'un autre compte.
     */
    public function claimOrdersByEmail(MarketCustomerAccount $account): int
    {
        return RetailOrder::query()
            ->withoutGlobalScope('company')
            ->whereNull('customer_account_id')
            ->where('source', RetailOrderSource::Online->value)
            ->whereRaw('LOWER(customer_email) = ?', [mb_strtolower($account->email)])
            ->update(['customer_account_id' => $account->id]);
    }

    /**
     * « Mes commandes » cross-boutiques : uniquement les commandes du compte
     * (bornage strict par customer_account_id), triées de la plus récente à
     * la plus ancienne, avec les lignes pour l'affichage.
     *
     * @return LengthAwarePaginator<int, RetailOrder>
     */
    public function ordersFor(MarketCustomerAccount $account, int $perPage = 20): LengthAwarePaginator
    {
        return RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('customer_account_id', $account->id)
            ->where('source', RetailOrderSource::Online->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
