<?php

declare(strict_types=1);

namespace App\Modules\Geo\Infrastructure\Services;

use App\Modules\Geo\Domain\Exceptions\UnknownSearchableTypeException;
use App\Shared\Contracts\Geo\GeoLocatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * GEO-04 (#8353, BC-33 GEO) — registry des types recherchables « le plus
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
 */
final class SearchableRegistry
{
    /** @var array<string, class-string<Model&GeoLocatable>> */
    private array $types = [];

    /**
     * Le class-string est vérifié à l'exécution (appels runtime/config) :
     * seuls les modèles Eloquent implémentant GeoLocatable sont acceptés.
     *
     * @param  class-string  $modelClass
     */
    public function register(string $type, string $modelClass): void
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
