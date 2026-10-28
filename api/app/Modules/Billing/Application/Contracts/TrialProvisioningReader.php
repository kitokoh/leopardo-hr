<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Contracts;

/**
 * Lecture de l'état d'un provisioning d'essai (#7962).
 *
 * Contrat placé dans Application/ pour que l'Action
 * `ReportTrialProvisioningStatus` n'importe pas la façade `DB` : le validateur
 * de structure de modules (#6568) réserve les façades Laravel à
 * Interfaces/Infrastructure. Une Action qui requête la base directement mélange
 * deux couches, même quand la requête tient en trois lignes.
 */
interface TrialProvisioningReader
{
    /**
     * @return object|null Ligne de `trial_provisionings` correspondant au token,
     *                     ou null si le token est inconnu.
     */
    public function findByToken(string $token): ?object;
}
