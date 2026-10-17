<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;

/**
 * REST-OS-2 (Mission Leopardo Restaurant OS) — Port de persistance des travaux
 * d'impression thermique (tenant-scoped).
 *
 * Même pattern que RestaurantOrderRepositoryInterface : scoping tenant
 * systématique (hors tenant → null → 404 sûr).
 *
 * REST-OS-5 (garde de pureté #6568) : les transitions d'état ATOMIQUES vivent
 * ici et non dans `Application/` — l'Application ne touche pas les facades
 * Laravel, tout accès aux données passe par ce port.
 */
interface RestaurantPrintJobRepositoryInterface
{
    /**
     * Charge un travail scopé au tenant. null si absent OU hors tenant.
     */
    public function findForCompany(int $id, string $companyId): ?RestaurantPrintJob;

    /**
     * Charge un travail par sa clé métier (commande, type, révision) — la même
     * clé que la contrainte unique : sert à l'idempotence applicative.
     */
    public function findByKey(
        string $companyId,
        int $orderId,
        PrintTicketKind $kind,
        int $revision,
    ): ?RestaurantPrintJob;

    /**
     * Réclamation ATOMIQUE : ne réclame que `pending`, `failed`, ou `claimed`
     * dont le bail est expiré. Retourne le nombre de lignes affectées (1 = ce
     * appelant a gagné, 0 = un autre bridge l'a réclamé ou l'état est terminal).
     */
    public function claim(int $id, string $companyId, ?string $printerRef, int $leaseMinutes): int;

    /**
     * Accusé d'impression RÉELLE. Ne transitionne jamais un travail déjà
     * terminal (idempotence). Retourne 1 si cet appel a effectivement changé
     * l'état (seul cas où l'événement outbox doit être publié), 0 sinon.
     */
    public function markPrinted(int $id, string $companyId, ?string $printerRef): int;

    /**
     * Échec d'impression signalé par le bridge (redevient réclamable).
     * Retourne 1 si l'état a effectivement changé, 0 sinon.
     */
    public function markFailed(int $id, string $companyId, string $error): int;
}
