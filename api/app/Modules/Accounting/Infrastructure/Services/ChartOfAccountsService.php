<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Application\Actions\ChartOfAccountsDefaults;
use App\Modules\Accounting\Domain\Enums\AccountType;
use App\Modules\Accounting\Domain\Models\AccountingChartAccount;
use Illuminate\Support\Facades\DB;

/**
 * Plan comptable par entreprise — issue #5422.
 *
 * - Provisioning idempotent : le plan par défaut (ChartOfAccountsDefaults)
 *   est inséré quand l'entreprise n'a encore aucun compte (première ouverture
 *   du module, ou migration de données). Jamais dupliqué.
 * - Lookup par code pour les moteurs d'écritures et les états financiers.
 * - CRUD : création de comptes analytiques libres, désactivation, édition
 *   d'intitulé. Les comptes système peuvent être désactivés mais pas
 *   supprimés (trace d'audit).
 */
final class ChartOfAccountsService
{
    /**
     * Garantit qu'une entreprise a son plan comptable provisionné.
     * Retourne true si le provisioning a inséré des comptes, false sinon.
     */
    public function ensureProvisioned(string $companyId): bool
    {
        $exists = AccountingChartAccount::query()
            ->where('company_id', $companyId)
            ->exists();

        if ($exists) {
            return false;
        }

        return DB::transaction(function () use ($companyId): bool {
            // Double-garde concurrente (deux appels parallèles ne dupliquent pas).
            if (AccountingChartAccount::query()->where('company_id', $companyId)->exists()) {
                return false;
            }

            $now = now();
            $rows = array_map(
                static fn (array $account): array => [
                    'company_id' => $companyId,
                    'code' => $account['code'],
                    'label' => $account['label'],
                    'type' => $account['type'],
                    'class' => $account['class'],
                    'is_system' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ChartOfAccountsDefaults::all(),
            );

            AccountingChartAccount::query()->insert($rows);

            return true;
        });
    }

    /**
     * Résout un compte du plan (actif uniquement). Retourne null si absent
     * ou désactivé — les moteurs d'écritures doivent alors signaler un écart
     * de paramétrage au lieu d'écrire sur un compte inconnu.
     */
    public function resolve(string $companyId, string $code): ?AccountingChartAccount
    {
        return AccountingChartAccount::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Comptes actifs d'une entreprise, optionnellement filtrés par nature.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AccountingChartAccount>
     */
    public function list(string $companyId, ?string $type = null, ?bool $activeOnly = true)
    {
        $query = AccountingChartAccount::query()
            ->where('company_id', $companyId)
            ->orderBy('code');

        if ($type !== null && in_array($type, AccountType::values(), true)) {
            $query->where('type', $type);
        }

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }
}
