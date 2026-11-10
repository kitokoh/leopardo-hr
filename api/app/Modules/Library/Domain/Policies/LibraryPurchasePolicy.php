<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Library\Domain\Models\LibraryPurchase;

/**
 * RBAC des ventes de la bibliothèque (BC-40 LIBRARY, #9238).
 *
 * Règle unique : un employé ne voit et ne confirme QUE ses propres achats, dans
 * son propre tenant. `deny-by-default`.
 *
 * ⚠️ Un achat n'est pas une donnée publique : le fait qu'un collègue ait
 * acheté un livre n'est pas exposé, même à un responsable. L'agrégat
 * comptable (chiffre d'affaires de la bibliothèque) relève d'un rapport
 * séparé, jamais de cette surface.
 */
class LibraryPurchasePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, LibraryPurchase $purchase): bool
    {
        return $this->owns($actor, $purchase);
    }

    public function create(Employee $actor): bool
    {
        return true;
    }

    public function confirm(Employee $actor, LibraryPurchase $purchase): bool
    {
        return $this->owns($actor, $purchase);
    }

    private function owns(Employee $actor, LibraryPurchase $purchase): bool
    {
        return (int) $purchase->employee_id === (int) $actor->id
            && (string) $purchase->company_id === (string) $actor->company_id;
    }
}
