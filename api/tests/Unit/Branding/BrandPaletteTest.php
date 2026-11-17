<?php

declare(strict_types=1);

namespace Tests\Unit\Branding;

use App\Modules\Branding\Domain\Palette\BrandPalette;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * #9578 — la palette calcule le contraste, elle ne le reçoit pas.
 *
 * Ces tests sont volontairement des tests UNITAIRES purs (pas de base, pas de
 * HTTP) : le calcul de contraste est une fonction mathématique, et le tester
 * à travers une requête rendrait chaque cas lent et indirect.
 */
final class BrandPaletteTest extends TestCase
{
    public function test_white_text_on_black_background_is_maximum_contrast(): void
    {
        $this->assertSame(21.0, round(BrandPalette::contrastRatio('#FFFFFF', '#000000'), 2));
    }

    public function test_contrast_ratio_is_symmetric(): void
    {
        $this->assertSame(
            round(BrandPalette::contrastRatio('#0F766E', '#FFFFFF'), 4),
            round(BrandPalette::contrastRatio('#FFFFFF', '#0F766E'), 4),
        );
    }

    /**
     * Le cas qui justifie tout le value object : un vert clair avec du texte
     * blanc donne ~1.3:1, illisible. Le calcul doit choisir le texte sombre.
     */
    public function test_light_primary_gets_dark_foreground(): void
    {
        $palette = BrandPalette::of('#A3E635', '#0D9488');

        $this->assertSame(BrandPalette::DARK_TEXT, $palette->onPrimary);
        $this->assertTrue($palette->meetsAaOnPrimary());
        $this->assertGreaterThanOrEqual(BrandPalette::AA_NORMAL, $palette->primaryContrastRatio());
    }

    public function test_dark_primary_gets_light_foreground(): void
    {
        $palette = BrandPalette::of('#0F172A', '#0D9488');

        $this->assertSame(BrandPalette::LIGHT_TEXT, $palette->onPrimary);
        $this->assertTrue($palette->meetsAaOnPrimary());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function aaCompliantPrimaries(): array
    {
        return [
            'teal' => ['#0F766E'],
            'navy' => ['#1E3A8A'],
            'lime' => ['#A3E635'],
            'yellow' => ['#FACC15'],
            'mid grey' => ['#6B7280'],
            'white' => ['#FFFFFF'],
            'black' => ['#000000'],
        ];
    }

    #[DataProvider('aaCompliantPrimaries')]
    public function test_every_primary_gets_an_aa_compliant_foreground(string $primary): void
    {
        $palette = BrandPalette::of($primary, '#0D9488');

        $this->assertTrue(
            $palette->meetsAaOnPrimary(),
            sprintf('Le contraste sur %s est de %.2f:1, sous AA.', $primary, $palette->primaryContrastRatio()),
        );
    }

    public function test_invalid_color_is_rejected_not_silently_corrected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BRAND_PALETTE_INVALID_COLOR');

        BrandPalette::of('red', '#000000');
    }

    public function test_short_hex_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        BrandPalette::of('#fff', '#000000');
    }

    /**
     * Une valeur corrompue en base ne doit pas casser le rendu public : on
     * retombe sur la palette par défaut plutôt que de lever dans le chemin
     * d'une requête anonyme.
     */
    public function test_corrupted_stored_palette_falls_back_to_default(): void
    {
        $palette = BrandPalette::fromArray(['primary' => 'not-a-color', 'accent' => '#0D9488']);

        $this->assertSame(BrandPalette::default()->primary, $palette->primary);
    }

    /**
     * `on_primary` stocké est ignoré : le calcul est la source de vérité.
     */
    public function test_stored_on_primary_is_ignored_and_recomputed(): void
    {
        $palette = BrandPalette::fromArray([
            'primary' => '#A3E635',
            'accent' => '#0D9488',
            'surface' => '#FFFFFF',
            'on_primary' => '#FFFFFF', // valeur imposée, contraste ~1.3:1
        ]);

        $this->assertSame(BrandPalette::DARK_TEXT, $palette->onPrimary);
        $this->assertTrue($palette->meetsAaOnPrimary());
    }

    public function test_colors_are_normalized_to_lowercase(): void
    {
        $palette = BrandPalette::of('#A3E635', '#0D9488', '#F8FAFC');

        $this->assertSame('#a3e635', $palette->primary);
        $this->assertSame('#0d9488', $palette->accent);
        $this->assertSame('#f8fafc', $palette->surface);
    }
}
