<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Infrastructure\Services;

use App\Modules\Paiements\Domain\Enums\FeeMode;
use App\Modules\Paiements\Domain\Models\PaymentFeeRule;

/**
 * BC-38 PAIEMENTS (#8687) — resolution des commissions.
 *
 * Cascade de specificite : la regle la plus precise gagne.
 *   tenant + instance (3) > tenant (2) > instance (1) > globale (0)
 * A specificite egale, `priority` croissante tranche, puis `id` pour un ordre
 * stable.
 *
 * **Aucune regle = commission zero.** Jamais d'exception : une plateforme sans
 * regle de commission ne doit pas empecher un paiement de passer.
 *
 * Unites : `fixed` = plus petite unite de la devise ; `percentage` = points de
 * base (1 % = 100), pour rester en arithmetique entiere.
 */
class FeeRuleResolver
{
    public function resolve(
        string $operationType,
        ?string $companyId = null,
        ?int $providerInstanceId = null,
    ): ?PaymentFeeRule {
        $rules = PaymentFeeRule::query()
            ->where('operation_type', $operationType)
            ->where(function ($query) use ($companyId): void {
                $query->whereNull('company_id');

                if ($companyId !== null) {
                    $query->orWhere('company_id', $companyId);
                }
            })
            ->where(function ($query) use ($providerInstanceId): void {
                $query->whereNull('provider_instance_id');

                if ($providerInstanceId !== null) {
                    $query->orWhere('provider_instance_id', $providerInstanceId);
                }
            })
            ->get();

        if ($rules->isEmpty()) {
            return null;
        }

        $sorted = $rules->sort(static function (PaymentFeeRule $a, PaymentFeeRule $b): int {
            return [$b->specificity(), $a->priority, $a->id] <=> [$a->specificity(), $b->priority, $b->id];
        })->values();

        return $sorted->first();
    }

    /**
     * Commission applicable, en plus petite unite de la devise.
     */
    public function feeFor(
        int $amount,
        string $operationType,
        ?string $companyId = null,
        ?int $providerInstanceId = null,
    ): int {
        $rule = $this->resolve($operationType, $companyId, $providerInstanceId);

        if ($rule === null) {
            return 0;
        }

        $mode = $rule->mode;

        $fee = match ($mode) {
            FeeMode::Fixed => $rule->value,
            FeeMode::Percentage => intdiv($amount * $rule->value, 10000),
            FeeMode::Mixed => $rule->value + intdiv($amount * $rule->value, 10000),
        };

        if ($rule->min_amount !== null && $fee < $rule->min_amount) {
            $fee = $rule->min_amount;
        }

        if ($rule->max_amount !== null && $fee > $rule->max_amount) {
            $fee = $rule->max_amount;
        }

        return max(0, $fee);
    }
}
