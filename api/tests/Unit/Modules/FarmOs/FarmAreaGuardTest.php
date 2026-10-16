<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\FarmOs;

use App\Modules\FarmOs\Domain\Enums\AreaSource;
use App\Modules\FarmOs\Domain\Enums\AreaUnit;
use App\Modules\FarmOs\Domain\Exceptions\FarmAreaMeasurementException;
use App\Modules\FarmOs\Domain\Services\FarmAreaGuard;
use PHPUnit\Framework\TestCase;

/**
 * Farm OS — Garde d'honnêteté des superficies (ADR-FARM-0003).
 *
 * Vérifie les trois règles appliquées côté serveur : valeur + unité ensemble,
 * `measured` interdit sans géométrie, et surface saisie ⇒ `declared`.
 */
final class FarmAreaGuardTest extends TestCase
{
    private FarmAreaGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new FarmAreaGuard;
    }

    public function test_value_and_unit_must_be_provided_together(): void
    {
        $this->expectException(FarmAreaMeasurementException::class);

        $this->guard->assertConsistent(2.5, null, null, false);
    }

    public function test_unit_without_value_is_refused(): void
    {
        $this->expectException(FarmAreaMeasurementException::class);

        $this->guard->assertConsistent(null, AreaUnit::Hectare, null, false);
    }

    public function test_measured_source_requires_geometry(): void
    {
        $this->expectException(FarmAreaMeasurementException::class);

        $this->guard->assertConsistent(2.5, AreaUnit::Hectare, AreaSource::Measured, false);
    }

    public function test_measured_source_is_accepted_with_geometry(): void
    {
        $this->expectNotToPerformAssertions();

        $this->guard->assertConsistent(2.5, AreaUnit::Hectare, AreaSource::Measured, true);
    }

    public function test_declared_source_is_accepted_without_geometry(): void
    {
        $this->expectNotToPerformAssertions();

        $this->guard->assertConsistent(12, AreaUnit::Hectare, AreaSource::Declared, false);
    }

    public function test_a_typed_area_defaults_to_declared_never_measured(): void
    {
        $this->assertSame(AreaSource::Declared, $this->guard->resolveSource(12, null));
        $this->assertNull($this->guard->resolveSource(null, null));
        $this->assertSame(AreaSource::Imported, $this->guard->resolveSource(12, AreaSource::Imported));
    }

    public function test_hectares_convert_to_square_meters_without_binary_float_drift(): void
    {
        $this->assertSame('10000.0000', $this->guard->toSquareMeters('1', AreaUnit::Hectare));
        $this->assertSame('25000.0000', $this->guard->toSquareMeters('2.5', AreaUnit::Hectare));
        $this->assertNull($this->guard->toSquareMeters(null, AreaUnit::Hectare));
    }
}
