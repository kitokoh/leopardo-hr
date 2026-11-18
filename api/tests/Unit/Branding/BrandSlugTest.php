<?php

declare(strict_types=1);

namespace Tests\Unit\Branding;

use App\Modules\Branding\Domain\Exceptions\BrandSlugException;
use App\Modules\Branding\Domain\Models\Brand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * #9578 — invariants de l'agrégat Brand.
 *
 * Ces tests n'ont pas besoin de base : les règles testées (slug, transitions,
 * locales, identité) sont des fonctions pures du modèle. Les invariants qui
 * dépendent du SCHÉMA (unicité du défaut, unicité du host) sont couverts par
 * les tests de feature, parce que c'est la base qui les garantit.
 */
/**
 * #9578 — invariants de l'agrégat Brand.
 *
 * Ces tests n'ont pas besoin de base : les règles testées (slug, transitions,
 * locales, identité) sont des fonctions pures du modèle. Les invariants qui
 * dépendent du SCHÉMA (unicité du défaut, unicité du host) sont couverts par
 * les tests de feature, parce que c'est la base qui les garantit.
 */
final class BrandSlugTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function normalizableSlugs(): array
    {
        return [
            'simple' => ['chez-lea', 'chez-lea'],
            'uppercase' => ['ChezLea', 'chezlea'],
            'spaces' => ['Chez Lea', 'chez-lea'],
            'accents' => ['Café Léa', 'cafe-lea'],
            'turkish dotless i' => ['Işık', 'isik'],
            'multiple separators' => ['chez   lea', 'chez-lea'],
            'leading/trailing dashes' => ['--chez-lea--', 'chez-lea'],
            'underscores' => ['chez_lea', 'chez-lea'],
            'digits' => ['leopardo 24', 'leopardo-24'],
        ];
    }

    #[DataProvider('normalizableSlugs')]
    public function test_slug_is_normalized(string $raw, string $expected): void
    {
        $this->assertSame($expected, Brand::normalizeSlug($raw));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonNormalizableSlugs(): array
    {
        return [
            'empty' => [''],
            'spaces only' => ['   '],
            'punctuation only' => ['!!!'],
            'emoji only' => ['🍕'],
        ];
    }

    #[DataProvider('nonNormalizableSlugs')]
    public function test_unusable_slug_returns_null(string $raw): void
    {
        $this->assertNull(Brand::normalizeSlug($raw));
    }

    public function test_reserved_slug_is_rejected(): void
    {
        $this->expectException(BrandSlugException::class);
        $this->expectExceptionMessage('BRAND_SLUG_RESERVED');

        Brand::assertValidSlug('admin');
    }

    public function test_invalid_slug_raises(): void
    {
        $this->expectException(BrandSlugException::class);
        $this->expectExceptionMessage('BRAND_SLUG_INVALID');

        Brand::assertValidSlug('!!!');
    }

    public function test_valid_slug_is_returned_normalized(): void
    {
        $this->assertSame('cafe-lea', Brand::assertValidSlug('Café Léa'));
    }

    /**
     * Un slug de 160 caractères est la borne haute acceptée.
     */
    public function test_slug_length_boundary(): void
    {
        $ok = str_repeat('a', 160);
        $this->assertSame($ok, Brand::normalizeSlug($ok));

        $tooLong = str_repeat('a', 161);
        $this->assertNull(Brand::normalizeSlug($tooLong));
    }
}

/**
 * #9578 — transitions de statut.
 */
