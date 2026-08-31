<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Contracts;

/**
 * Source de contacts pour l'évaluation d'un segment — Issue #5723.
 *
 * Contrat d'extension : le service SegmentService reconstruit les membres
 * d'un segment via cette interface, jamais via du SQL utilisateur. Les
 * implémentations doivent être tenant-scopées (company_id courant) et
 * retourner la liste des contact_id correspondant à la définition.
 */
interface SegmentContactSourceInterface
{
    /**
     * Contact_id (tenant-scopés) correspondant à la définition validée.
     *
     * @param  array{operator: string, conditions: list<array{field: string, op: string, value: mixed}>}  $definition
     * @return list<int>
     */
    public function matchingContactIds(array $definition): array;

    /**
     * La source peut-elle évaluer cette définition aujourd'hui ?
     * (ex. tables CRM pas encore migrées → false, rebuild no-op documenté.)
     *
     * @param  array{operator: string, conditions: list<array{field: string, op: string, value: mixed}>}  $definition
     */
    public function supports(array $definition): bool;
}
