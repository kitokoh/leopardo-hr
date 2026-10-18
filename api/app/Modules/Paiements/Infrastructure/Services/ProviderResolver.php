<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Infrastructure\Services;

use App\Modules\Paiements\Domain\Enums\ProviderInstanceStatus;
use App\Modules\Paiements\Domain\Exceptions\ProviderNotResolvableException;
use App\Modules\Paiements\Domain\Models\PaymentProviderInstance;

/**
 * BC-38 PAIEMENTS (#8686) — resolution de l'instance a utiliser pour un marche.
 *
 * C'est LE point unique de choix d'un fournisseur. Aucun BC consommateur ne
 * doit decider lui-meme quel moyen de paiement utiliser : il demande un marche
 * (pays + devise + tenant) et le resolver repond.
 *
 * Ordre de resolution :
 *   1. instances **resolvables** uniquement (`active` / `sandbox`) — une
 *      instance `inactive` est exclue par construction, jamais par convention ;
 *   2. couverture du marche (pays + devise) ;
 *   3. instance dediee au tenant **avant** l'instance globale ;
 *   4. `priority` croissante (plus petit = plus prioritaire), puis `id` pour un
 *      ordre stable et deterministe.
 *
 * Aucune instance compatible -> `ProviderNotResolvableException` : une erreur
 * metier explicite, jamais un `null` silencieux qui remonterait en 500 opaque.
 */
class ProviderResolver
{
    public function resolve(string $country, string $currency, ?string $companyId = null): PaymentProviderInstance
    {
        $candidates = $this->candidates($country, $currency, $companyId);

        if ($candidates === []) {
            throw ProviderNotResolvableException::forMarket($country, $currency, $companyId);
        }

        return $candidates[0];
    }

    public function resolveOrNull(string $country, string $currency, ?string $companyId = null): ?PaymentProviderInstance
    {
        $candidates = $this->candidates($country, $currency, $companyId);

        return $candidates[0] ?? null;
    }

    /**
     * @return list<PaymentProviderInstance>
     */
    public function candidates(string $country, string $currency, ?string $companyId = null): array
    {
        $instances = PaymentProviderInstance::query()
            ->whereIn('status', [
                ProviderInstanceStatus::Active->value,
                ProviderInstanceStatus::Sandbox->value,
            ])
            ->where(function ($query) use ($companyId): void {
                $query->whereNull('company_id');

                if ($companyId !== null) {
                    $query->orWhere('company_id', $companyId);
                }
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $matching = [];

        foreach ($instances as $instance) {
            if (! $instance->coversMarket($country, $currency)) {
                continue;
            }

            $matching[] = $instance;
        }

        // Instance dediee au tenant prioritaire sur l'instance globale, a
        // priorite egale : c'est ce qui permet a un tenant de surcharger un
        // moyen de paiement global sans toucher au reste de la plateforme.
        usort($matching, static function (PaymentProviderInstance $a, PaymentProviderInstance $b): int {
            $aDedicated = $a->company_id !== null ? 0 : 1;
            $bDedicated = $b->company_id !== null ? 0 : 1;

            return [$aDedicated, $a->priority, $a->id] <=> [$bDedicated, $b->priority, $b->id];
        });

        return $matching;
    }
}
