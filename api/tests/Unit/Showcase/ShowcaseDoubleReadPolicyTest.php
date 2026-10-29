<?php

declare(strict_types=1);

namespace Tests\Unit\Showcase;

use App\Modules\Showcase\Domain\Support\ShowcaseDoubleReadPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * #8727 — politique de double lecture Showcase ↔ Pages.
 *
 * Ces tests portent sur la seule partie décidable sans base : la
 * comparaison, l'échantillonnage et le rendu. C'est délibéré — une divergence
 * mal classée (bruit sur du parc non backfillé, ou silence sur un statut
 * différent) rendrait la double lecture inutile en production.
 */
final class ShowcaseDoubleReadPolicyTest extends TestCase
{
    #[Test]
    public function le_rollout_a_zero_ne_double_rien(): void
    {
        self::assertFalse(ShowcaseDoubleReadPolicy::shouldSample('boulangerie-centrale', 0));
        self::assertFalse(ShowcaseDoubleReadPolicy::shouldSample('boulangerie-centrale', -10));
    }

    #[Test]
    public function le_rollout_a_cent_double_tout(): void
    {
        self::assertTrue(ShowcaseDoubleReadPolicy::shouldSample('boulangerie-centrale', 100));
        self::assertTrue(ShowcaseDoubleReadPolicy::shouldSample('nimporte-quoi', 150));
    }

    #[Test]
    public function l_echantillonnage_est_deterministe_par_slug(): void
    {
        // Propriétés exigées par le rollout progressif : un même slug tombe
        // toujours du même côté, et monter le pourcentage ne retire jamais
        // une vitrine déjà échantillonnée.
        $slug = 'restaurant-leopardo';

        $verdicts = array_map(
            static fn (): bool => ShowcaseDoubleReadPolicy::shouldSample($slug, 50),
            range(1, 20),
        );

        self::assertCount(1, array_unique($verdicts), 'Le tirage doit être stable pour un slug donné.');

        $dejaEchantillonne = false;

        foreach ([10, 25, 50, 75, 100] as $percent) {
            $inclus = ShowcaseDoubleReadPolicy::shouldSample($slug, $percent);

            if ($dejaEchantillonne) {
                self::assertTrue($inclus, 'Un rollout monotone ne doit jamais retirer une vitrine.');
            }

            $dejaEchantillonne = $inclus;
        }
    }

    #[Test]
    public function une_vitrine_sans_page_projetee_n_est_pas_une_divergence(): void
    {
        // Cas NORMAL avant le premier backfill : signaler du bruit ici
        // rendrait la double lecture illisible dès qu'elle est activée.
        $divergences = ShowcaseDoubleReadPolicy::compare(
            ['status' => 'published', 'slug' => 'x', 'sections' => 3],
            null,
        );

        self::assertSame([], $divergences);
    }

    #[Test]
    public function une_projection_identique_est_en_parite(): void
    {
        $lecture = ['status' => 'published', 'slug' => 'boulangerie', 'sections' => 7];

        self::assertSame([], ShowcaseDoubleReadPolicy::compare($lecture, $lecture));
    }

    #[Test]
    public function un_statut_divergent_est_signale(): void
    {
        $divergences = ShowcaseDoubleReadPolicy::compare(
            ['status' => 'published', 'slug' => 'boulangerie', 'sections' => 7],
            ['status' => 'draft', 'slug' => 'boulangerie', 'sections' => 7],
        );

        self::assertCount(1, $divergences);
        self::assertStringContainsString('status: legacy=published pages=draft', $divergences[0]);
    }

    #[Test]
    public function un_slug_divergent_est_signale(): void
    {
        $divergences = ShowcaseDoubleReadPolicy::compare(
            ['status' => 'published', 'slug' => 'boulangerie', 'sections' => 7],
            ['status' => 'published', 'slug' => 'boulangerie-2', 'sections' => 7],
        );

        self::assertCount(1, $divergences);
        self::assertStringContainsString('slug:', $divergences[0]);
    }

    #[Test]
    public function un_ecart_de_sections_est_signale(): void
    {
        // Contenu manquant à la projection : c'est précisément ce que la
        // migration ne doit pas laisser passer.
        $divergences = ShowcaseDoubleReadPolicy::compare(
            ['status' => 'published', 'slug' => 'boulangerie', 'sections' => 7],
            ['status' => 'published', 'slug' => 'boulangerie', 'sections' => 5],
        );

        self::assertCount(1, $divergences);
        self::assertStringContainsString('sections: legacy=7 pages=5', $divergences[0]);
    }

    #[Test]
    public function le_resume_distingue_parite_et_divergence(): void
    {
        self::assertSame(
            'showcase:boulangerie double-read=parity',
            ShowcaseDoubleReadPolicy::summary('boulangerie', []),
        );

        $resume = ShowcaseDoubleReadPolicy::summary('boulangerie', ['sections: legacy=7 pages=5']);

        self::assertStringContainsString('double-read=DIVERGENCE', $resume);
        self::assertStringContainsString('sections: legacy=7 pages=5', $resume);
    }

    #[Test]
    public function le_statut_attendu_suit_le_statut_de_vitrine(): void
    {
        self::assertSame('published', ShowcaseDoubleReadPolicy::expectedPageStatus('published'));
        self::assertSame('draft', ShowcaseDoubleReadPolicy::expectedPageStatus('draft'));
    }
}
