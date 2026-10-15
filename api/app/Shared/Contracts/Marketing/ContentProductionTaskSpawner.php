<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Marketing;

/**
 * Contrat partagé : créer une tâche de production à partir d'un contenu
 * marketing (Marketing OS, brief §B — « transformer une idée en tâche de
 * production sans perdre les informations déjà saisies »).
 *
 * Pourquoi un contrat dans `app/Shared/Contracts/` plutôt qu'un appel
 * direct : la garde CI d'isolation inter-modules (#5584) interdit à
 * `Modules/Marketing` d'importer `Modules/Planning` (et inversement).
 * `app/Shared` n'est pas un module : c'est le canal prévu pour ce type de
 * collaboration, comme `PublishedProductsProvider` (Catalog → Showcase) ou
 * `EmailContactDirectory` (CRM → Communication).
 *
 * Le module consommateur (Marketing) ne dépend donc que de cette interface ;
 * l'implémentation vit dans le module propriétaire (Planning) et est liée
 * par son ServiceProvider.
 */
interface ContentProductionTaskSpawner
{
    /**
     * Crée une tâche de production et retourne son identifiant.
     *
     * @param  array{
     *     title: string,
     *     description: string,
     *     due_date: string,
     *     priority: string,
     *     category: string,
     *     created_by?: int|null,
     * }  $payload
     * @return int|null  Identifiant de la tâche créée, ou null si la
     *                   création n'a pas pu être réalisée (aucun échec
     *                   silencieux : l'appelant journalise).
     */
    public function spawn(array $payload): ?int;
}
