<?php

declare(strict_types=1);

namespace Tests\Unit\HR;

use App\Modules\HR\Infrastructure\Support\ContrastingTextColor;
use PHPUnit\Framework\TestCase;

/**
 * BOS-B-01 (Lot 7) — le libellé d'un bouton « couleur du tenant » doit rester
 * lisible.
 *
 * Le défaut réel : `primary_color` est libre, et le rendu posait un libellé
 * BLANC dessus. Un tenant qui choisissait un vert clair (#A3E635) obtenait un
 * contraste de ~1.3:1 — très en dessous du minimum WCAG AA de 4.5:1.
 */
final class ContrastingTextColorTest extends TestCase
{
    public function test_un_fond_clair_recoit_un_texte_sombre(): void
    {
        // Le cas qui a motivé le correctif : vert lime très clair.
        $this->assertSame(ContrastingTextColor::DARK, ContrastingTextColor::for('#A3E635'));
        $this->assertSame(ContrastingTextColor::DARK, ContrastingTextColor::for('#FFFFFF'));
        $this->assertSame(ContrastingTextColor::DARK, ContrastingTextColor::for('#FDE68A'));
    }

    public function test_un_fond_sombre_recoit_un_texte_clair(): void
    {
        $this->assertSame(ContrastingTextColor::LIGHT, ContrastingTextColor::for('#111827'));
        $this->assertSame(ContrastingTextColor::LIGHT, ContrastingTextColor::for('#155E75'));
        $this->assertSame(ContrastingTextColor::LIGHT, ContrastingTextColor::for('#7C3AED'));
    }

    public function test_le_choix_atteint_le_seuil_wcag_aa_pour_les_fonds_reels(): void
    {
        // La palette réellement utilisable par un tenant (défauts du produit
        // inclus) doit TOUS atteindre AA, pas seulement « paraître » lisible.
        $backgrounds = [
            '#10B981', // défaut Leopardo
            '#2563EB', // accent par défaut
            '#A3E635',
            '#FDE68A',
            '#111827',
            '#FFFFFF',
            '#F3F4F6',
            '#DC2626',
            '#0EA5E9',
        ];

        foreach ($backgrounds as $background) {
            $foreground = ContrastingTextColor::for($background);
            $ratio = ContrastingTextColor::ratioBetween($background, $foreground);

            $this->assertGreaterThanOrEqual(
                ContrastingTextColor::AA_NORMAL,
                $ratio,
                "Contraste insuffisant pour {$background} / {$foreground} : {$ratio}:1"
            );
        }
    }

    public function test_une_couleur_invalide_ne_casse_pas_le_rendu(): void
    {
        // Le chemin de rendu d'un payload ne doit jamais lever : on retombe
        // sur le comportement historique (texte clair).
        $this->assertSame(ContrastingTextColor::LIGHT, ContrastingTextColor::for('pas-une-couleur'));
        $this->assertSame(ContrastingTextColor::LIGHT, ContrastingTextColor::for(''));
        $this->assertSame(1.0, ContrastingTextColor::ratioBetween('#GGGGGG', '#FFFFFF'));
    }

    public function test_la_forme_courte_hex_est_acceptee(): void
    {
        // `#fff` et `#000` sont des écritures courantes côté client.
        $this->assertSame(ContrastingTextColor::DARK, ContrastingTextColor::for('#fff'));
        $this->assertSame(ContrastingTextColor::LIGHT, ContrastingTextColor::for('#000'));
    }
}
