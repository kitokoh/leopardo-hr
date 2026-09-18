<?php

declare(strict_types=1);

namespace App\Core\Tenant\Infrastructure\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Issue #7598 (R1 de l'épique #7597) — lecture du registre des types de
 * ressource (`config/resource_types.php`).
 *
 * Le registre répond à trois questions, et refuse de répondre à toute autre :
 *  1. ce type existe-t-il ? (`RESOURCE_TYPE_UNKNOWN` sinon — fail-closed : un
 *     type non déclaré n'ouvre JAMAIS les vannes par défaut) ;
 *  2. cette ressource existe-t-elle **dans cette entreprise** ? (un
 *     identifiant valide d'un autre tenant ne doit pas être assignable) ;
 *  3. quelles ressources de ce type existent, et comment les nommer ?
 *
 * Aucun import de module ici : le registre lit des chaînes de classes
 * déclarées en configuration, ce qui évite qu'un module en importe un autre
 * pour s'inscrire (R3 étendra la liste, pas ce service).
 */
class ResourceTypeRegistry
{
    /**
     * @return array<string, array{model: class-string<Model>, label_column: string, scope_company: bool}>
     */
    public function types(): array
    {
        /** @var array<string, array{model: class-string<Model>, label_column: string, scope_company: bool}> $types */
        $types = config('resource_types', []);

        return $types;
    }

    /**
     * Clés déclarées — sert aux règles de validation (`Rule::in`).
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->types());
    }

    public function has(string $type): bool
    {
        return array_key_exists($type, $this->types());
    }

    /**
     * La ressource `$id` du type `$type` existe-t-elle pour `$companyId` ?
     */
    public function exists(string $type, int $resourceId, ?string $companyId): bool
    {
        $model = $this->modelFor($type);

        if ($model === null) {
            return false;
        }

        $query = $model->newQuery()->whereKey($resourceId);

        if ($this->scopesToCompany($type) && $companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->exists();
    }

    /**
     * Ressources de ce type visibles pour l'entreprise, optionnellement
     * restreintes à une liste d'identifiants (`null` = aucune restriction).
     *
     * @param  list<int>|null  $onlyIds
     * @return list<array{id: int, label: string}>
     */
    public function listForCompany(string $type, ?string $companyId, ?array $onlyIds = null): array
    {
        $model = $this->modelFor($type);

        if ($model === null) {
            return [];
        }

        // Liste vide = accès à rien (scoping actif sans assignation) : on
        // n'interroge même pas, le résultat est vide par construction.
        if ($onlyIds === []) {
            return [];
        }

        $query = $model->newQuery();

        if ($this->scopesToCompany($type) && $companyId !== null) {
            $query->where('company_id', $companyId);
        }

        if ($onlyIds !== null) {
            $query->whereIn('id', $onlyIds);
        }

        $labelColumn = (string) ($this->types()[$type]['label_column'] ?? 'id');

        /** @var list<array{id: int, label: string}> $rows */
        $rows = [];
        foreach ($query->orderBy($labelColumn)->get() as $resource) {
            $rows[] = [
                'id' => (int) $resource->getKey(),
                'label' => (string) ($resource->getAttribute($labelColumn) ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * Modèle Eloquent d'un type, ou null si le type n'est pas déclaré.
     *
     * @return (Model&object)|null
     */
    private function modelFor(string $type): ?Model
    {
        if (! $this->has($type)) {
            return null;
        }

        /** @var class-string<Model> $class */
        $class = $this->types()[$type]['model'];

        return new $class;
    }

    private function scopesToCompany(string $type): bool
    {
        return (bool) ($this->types()[$type]['scope_company'] ?? true);
    }
}
