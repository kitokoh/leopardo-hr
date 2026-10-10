<?php

declare(strict_types=1);

namespace Tests\Unit\Geo;

use App\Modules\Geo\Domain\Exceptions\UnknownSearchableTypeException;
use App\Modules\Geo\Infrastructure\Services\SearchableRegistry;
use App\Shared\Contracts\Geo\GeoLocatable;
use App\Shared\Geo\GeoPoint;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * GEO-04 (#8353, BC-33 GEO) — registry opt-in des types recherchables :
 * enregistrement, résolution, refus fail-closed des types inconnus.
 */
class SearchableRegistryTest extends TestCase
{
    public function test_register_and_resolve(): void
    {
        $registry = new SearchableRegistry;
        $registry->register('fake_place', FakeLocatableModel::class);

        self::assertSame(FakeLocatableModel::class, $registry->resolve('fake_place'));
        self::assertSame(['fake_place' => FakeLocatableModel::class], $registry->all());
    }

    public function test_unknown_type_is_refused_fail_closed(): void
    {
        $registry = new SearchableRegistry;

        $this->expectException(UnknownSearchableTypeException::class);

        $registry->resolve('inconnu');
    }

    public function test_invalid_type_slug_is_rejected(): void
    {
        $registry = new SearchableRegistry;

        $this->expectException(InvalidArgumentException::class);

        $registry->register('Type Invalide!', FakeLocatableModel::class);
    }

    public function test_model_must_implement_geo_locatable(): void
    {
        $registry = new SearchableRegistry;

        $this->expectException(InvalidArgumentException::class);

        $registry->register('fake_place', PlainModel::class);
    }
}

/** Modèle factice conforme (GeoLocatable) pour la registry. */
class FakeLocatableModel extends Model implements GeoLocatable
{
    protected $table = 'geo_fake_places';

    public function geoPoint(): ?GeoPoint
    {
        $lat = $this->getAttribute('latitude');
        $lng = $this->getAttribute('longitude');

        return GeoPoint::fromNullable(
            is_numeric($lat) ? (float) $lat : null,
            is_numeric($lng) ? (float) $lng : null,
        );
    }

    public function geoLabel(): string
    {
        $label = $this->getAttribute('name');

        return is_string($label) ? $label : '';
    }

    public static function geoLatitudeColumn(): string
    {
        return 'latitude';
    }

    public static function geoLongitudeColumn(): string
    {
        return 'longitude';
    }
}

/** Modèle factice NON conforme (n'implémente pas GeoLocatable). */
class PlainModel extends Model
{
    protected $table = 'geo_fake_places';
}
