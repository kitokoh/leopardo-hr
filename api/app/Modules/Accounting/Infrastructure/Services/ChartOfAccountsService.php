<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\AccountType;
use App\Modules\Accounting\Domain\Models\AccountingChartAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Plan comptable par entreprise — issues #5422 et #7925.
 *
 * - Provisioning idempotent : le plan par défaut est inséré quand
 *   l'entreprise n'a encore aucun compte (première ouverture du module, ou
 *   migration de données). Jamais dupliqué. Le jeu de comptes est résolu
 *   depuis le pays du tenant (#7925) : SYSCOHADA pour la zone OHADA,
 *   Tekdüzen pour TR, plan nord-américain pour CA, PCG sinon — voir
 *   ChartOfAccountsDefaults::forCountry().
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
     *
     * Le pays et la langue du tenant sélectionnent le référentiel seedé
     * (#7925) ; s'ils ne sont pas fournis par l'appelant, ils sont résolus
     * depuis le registre des sociétés (public.companies).
     */
    public function ensureProvisioned(string $companyId, ?string $country = null, ?string $language = null): bool
    {
        $exists = AccountingChartAccount::query()
            ->where('company_id', $companyId)
            ->exists();

        if ($exists) {
            return false;
        }

        if ($country === null) {
            /** @var Company|null $company */
            $company = Company::query()->find($companyId);
            $country = $company?->country;
            $language ??= $company?->language;
        }

        return DB::transaction(function () use ($companyId, $country, $language): bool {
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
                ChartOfAccountsDefaults::forCountry($country, $language),
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
     * @return Collection<int, AccountingChartAccount>
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
