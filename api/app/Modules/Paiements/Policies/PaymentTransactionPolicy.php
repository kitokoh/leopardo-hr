<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Paiements\Domain\Models\PaymentTransaction;

/**
 * BC-38 PAIEMENTS (#8711) — autorisation explicite sur une transaction.
 *
 * Un tenant ne lit que ses propres transactions : le scope `BelongsToCompany`
 * le garantit deja au niveau requete, cette policy le garantit au niveau
 * objet — donc aussi pour un objet charge sans scope (admin, job, test).
 *
 * Seul le **proprietaire du tenant** engage de l'argent sur une transaction
 * (retrait, contre-passation) ; `rh` peut lire (paie), pas operer.
 */
class PaymentTransactionPolicy
{
    public function view(Employee $actor, PaymentTransaction $transaction): bool
    {
        return $this->belongsToActorTenant($actor, $transaction)
            && ($actor->isPrincipal() || $actor->isHr());
    }

    /**
     * Operation engageant le compte : proprietaire uniquement.
     */
    public function operate(Employee $actor, PaymentTransaction $transaction): bool
    {
        return $this->belongsToActorTenant($actor, $transaction)
            && $actor->isPrincipal();
    }

    private function belongsToActorTenant(Employee $actor, PaymentTransaction $transaction): bool
    {
        $actorCompany = $this->stringId($actor->company_id);
        $transactionCompany = $this->stringId($transaction->company_id);

        return $actorCompany !== null
            && $transactionCompany !== null
            && $actorCompany === $transactionCompany;
    }

    private function stringId(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
