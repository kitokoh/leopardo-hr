<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Contracts;

use App\Modules\CRM\Domain\Models\CrmImport;

/**
 * #5714 — Port de persistance des sessions d'import CSV.
 *
 * L'infrastructure (contrôleur, service, job) dépend de cette interface et
 * jamais du modèle Eloquent directement (ports & adapters, spec module §2).
 */
interface CrmImportRepositoryInterface
{
    /**
     * Charge une session d'import scopée au tenant courant.
     * Retourne null si absente OU hors tenant (404 sûr, jamais 403).
     */
    public function findForCompany(int $id, string $companyId): ?CrmImport;

    /**
     * Crée une session d'import en statut previewed.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createForCompany(string $companyId, int $actorId, array $attributes): CrmImport;

    /**
     * Claim atomique du commit : previewed|failed → committing.
     * Retourne false si la session n'est plus committable (idempotence).
     */
    public function claimCommit(CrmImport $import): bool;

    /**
     * Claim atomique de l'annulation : previewed|committing → cancelled.
     * Retourne false si la session n'est plus annulable.
     */
    public function claimCancel(CrmImport $import): bool;

    /**
     * Marque la session committed (succès partiel possible : résultat
     * détaillé dans le payload).
     *
     * @param  array<string, mixed>  $result
     */
    public function markCommitted(CrmImport $import, int $actorId, array $result): void;

    /**
     * Marque la session failed.
     *
     * @param  array<string, mixed>  $result
     */
    public function markFailed(CrmImport $import, array $result): void;
}
