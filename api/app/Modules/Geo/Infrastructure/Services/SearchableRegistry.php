<?php

declare(strict_types=1);

namespace App\Modules\Geo\Infrastructure\Services;

use App\Modules\Geo\Domain\Exceptions\UnknownSearchableTypeException;
use App\Shared\Contracts\Geo\GeoLocatable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * GEO-04 (#8353, BC-34 GEO) — registry des types recherchables « le plus
 * proche » (opt-in, fail-closed).
 *
 * Deux sources fusionnées :
 *  - config `geo.searchables` (déclaratif : type ⇒ class-string du modèle) ;
 *  - enregistrement runtime par les providers des verticales (inversion de
 *    dépendance — le module Geo ne référence JAMAIS une verticale, garde
 *    d'isolation #5584, même pattern que le SolutionCatalogue).
 *
 * Un type inconnu lève UnknownSearchableTypeException : aucune table tenant
 * n'est exposée sans enregistrement explicite.
 *
 * #8423 (M-02) — garde d'autorisation par type : un type enregistré peut
 * exiger un rôle (ex. positions chauffeurs réservées admin/dispatcher). La
 * verticale fournit une closure d'autorisation à l'enregistrement RUNTIME
 * (inversion de dépendance : le core ignore les rôles de chaque verticale) ;
 * elle est appliquée UNIQUEMENT à la couche HTTP (GeoNearestController) —
 * les appels internes (dispatch en worker, sans utilisateur authentifié)
 * passent par `nearest()` et ne sont JAMAIS bloqués par cette garde.
 */
final class SearchableRegistry
{
    /** @var array<string, class-string<Model&GeoLocatable>> */
    private array $types = [];

    /** @var array<string, Closure(\App\Core\Auth\Domain\Models\Employee): bool> */
    private array $authorizers = [];

    /**
     * Le class-string est vérifié à l'exécution (appels runtime/config) :
     * seuls les modèles Eloquent implémentant GeoLocatable sont acceptés.
     *
     * @param  class-string  $modelClass
     * @param  null|Closure(\App\Core\Auth\Domain\Models\Employee): bool  $authorizer
     *         Garde optionnelle : reçoit l'employé authentifié et autorise
     *         (true) ou refuse (false → 403) la recherche HTTP sur ce type.
     */
    public function register(string $type, string $modelClass, ?Closure $authorizer = null): void
    {
        if ($type === '' || ! preg_match('/^[a-z][a-z0-9_-]*$/', $type)) {
            throw new InvalidArgumentException("Type recherchable invalide : « {$type} » (slug attendu).");
        }

        if (! is_a($modelClass, Model::class, true) || ! is_a($modelClass, GeoLocatable::class, true)) {
            throw new InvalidArgumentException(
                "Le modèle {$modelClass} doit étendre ".Model::class.' et implémenter '.GeoLocatable::class.'.'
            );
        }

        $this->types[$type] = $modelClass;

        if ($authorizer !== null) {
            $this->authorizers[$type] = $authorizer;
        }
    }

    /**
     * @return class-string<Model&GeoLocatable>
     *
     * @throws UnknownSearchableTypeException
     */
    public function resolve(string $type): string
    {
        $types = $this->all();

        if (! isset($types[$type])) {
            throw new UnknownSearchableTypeException($type);
        }

        return $types[$type];
    }

    /**
     * Garde d'autorisation d'un type enregistré, null si le type est libre
     * d'accès pour tout utilisateur authentifié du tenant (#8423, M-02).
     *
     * Fail-closed conservé : un type inconnu lève la même 422 que resolve().
     *
     * @return null|Closure(\App\Core\Auth\Domain\Models\Employee): bool
     *
     * @throws UnknownSearchableTypeException
     */
    public function authorizerFor(string $type): ?Closure
    {
        $this->resolve($type);

        return $this->authorizers[$type] ?? null;
    }

    /**
     * Types enregistrés (config + runtime), triés par code — diagnostic GEO-05.
     *
     * @return array<string, class-string<Model&GeoLocatable>>
     */
    public function all(): array
    {
        /** @var mixed $configuredRaw */
        $configuredRaw = config('geo.searchables', []);
        $configured = is_array($configuredRaw) ? $configuredRaw : [];

        $types = $this->types;

        foreach ($configured as $type => $modelClass) {
            if (! is_string($type) || ! is_string($modelClass)) {
                continue;
            }

            if (is_a($modelClass, Model::class, true) && is_a($modelClass, GeoLocatable::class, true)) {
                $types[$type] = $modelClass;
            }
        }

        ksort($types);

        return $types;
    }
}
