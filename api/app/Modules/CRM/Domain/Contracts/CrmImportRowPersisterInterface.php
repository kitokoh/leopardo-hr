<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Contracts;

use App\Modules\CRM\Domain\Enums\CrmImportEntityType;

/**
 * #5714 — Port de persistance des lignes importées.
 *
 * Chaque entité importable (accounts/contacts/leads) expose une
 * implémentation qui mappe les lignes CSV validées vers son modèle
 * tenant-scoped. Le service d'import dépend de ce port — il ignore
 * totalement les détails Eloquent.
 */
interface CrmImportRowPersisterInterface
{
    /**
     * Entités supportées par cette implémentation.
     */
    public function supports(CrmImportEntityType $entityType): bool;

    /**
     * Persiste une ligne validée dans le tenant courant.
     *
     * @param  array<string, mixed>  $row  ligne CSV nettoyée (formules neutralisées)
     * @return int identifiant de la ligne créée
     */
    public function persistRow(CrmImportEntityType $entityType, array $row): int;
}
