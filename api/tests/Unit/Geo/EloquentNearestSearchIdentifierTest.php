<?php

declare(strict_types=1);

namespace Tests\Unit\Geo;

use App\Modules\Geo\Infrastructure\Services\EloquentNearestSearch;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * #9350 (audit sécurité 2026-11-10, F8) — verrouille l'invariant des
 * identifiants SQL géospatiaux.
 *
 * Les valeurs de latitude/longitude sont correctement liées (`?`), mais les
 * NOMS DE COLONNES sont interpolés dans la chaîne SQL. Le dépôt les protège
 * par une allowlist regex dans `wrapColumn()` — le constat est donc non
 * exploitable aujourd'hui. Ce test empêche la régression : toute nouvelle
 * méthode qui interpolerait un identifiant sans passer par l'allowlist, ou
 * tout relâchement de la regex, casse ici.
 *
 * Test unitaire pur (pas de base, pas de conteneur) : on invoque la méthode
 * privée par réflexion, ce qui est précisément le point à verrouiller.
 */
class EloquentNearestSearchIdentifierTest extends TestCase
{
    /**
     * @return mixed
     */
    private function wrap(string $column)
    {
        $method = new ReflectionMethod(EloquentNearestSearch::class, 'wrapColumn');
        $method->setAccessible(true);

        // `wrapColumn()` n'utilise le builder que pour lire la table du
        // modèle : un objet anonyme suffit, ce qui évite d'instancier le
        // service (dépendances `SearchableRegistry` + `GeoCapabilities`).
        $model = new class
        {
            public function getTable(): string
            {
                return 'fuel_stations';
            }
        };

        $builder = new class($model)
        {
            public function __construct(private readonly object $model) {}

            public function getModel(): object
            {
                return $this->model;
            }
        };

        return $method->invoke(new EloquentNearestSearch(
            new \App\Modules\Geo\Infrastructure\Services\SearchableRegistry,
            new \App\Modules\Geo\Infrastructure\Services\GeoCapabilities,
        ), $builder, $column);
    }

    public function test_it_quotes_a_legitimate_column(): void
    {
        $this->assertSame('"fuel_stations"."latitude"', $this->wrap('latitude'));
    }

    public function test_it_quotes_an_already_qualified_column(): void
    {
        $this->assertSame('"fuel_stations"."longitude"', $this->wrap('fuel_stations.longitude'));
    }

    public function test_it_rejects_a_quote_in_the_identifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->wrap('latitude"; DROP TABLE employees; --');
    }

    public function test_it_rejects_a_semicolon_in_the_identifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->wrap('latitude;SELECT 1');
    }

    public function test_it_rejects_a_comment_sequence(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->wrap('latitude--');
    }

    public function test_it_rejects_an_identifier_starting_with_a_digit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->wrap('1latitude');
    }
}
