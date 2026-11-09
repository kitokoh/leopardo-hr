<?php

declare(strict_types=1);

namespace Tests\Unit\Library;

use App\Modules\Library\Domain\Support\LibrarySemanticScorer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests du scoring sémantique du catalogue public (#9240).
 *
 * Le scorer est PUR : ces tests prouvent qu'il classe correctement, sans base
 * de données ni appel réseau.
 */
final class LibrarySemanticScorerTest extends TestCase
{
    #[Test]
    public function tokenize_retire_accents_ponctuation_et_mots_vides(): void
    {
        $tokens = LibrarySemanticScorer::tokenize('La Gestion des Stocks, et plus !');

        $this->assertContains('gestion', $tokens);
        $this->assertContains('stocks', $tokens);
        // Mots vides retirés.
        $this->assertNotContains('la', $tokens);
        $this->assertNotContains('des', $tokens);
        $this->assertNotContains('et', $tokens);
        $this->assertNotContains('plus', $tokens);
    }

    #[Test]
    public function tokenize_est_insensible_aux_accents(): void
    {
        $this->assertSame(
            LibrarySemanticScorer::tokenize('gestion'),
            LibrarySemanticScorer::tokenize('géstion'),
        );
    }

    #[Test]
    public function tokenize_deduplique_les_tokens(): void
    {
        $this->assertSame(['stock'], LibrarySemanticScorer::tokenize('stock stock stock'));
    }

    #[Test]
    public function tokenize_retourne_un_tableau_vide_pour_du_bruit(): void
    {
        $this->assertSame([], LibrarySemanticScorer::tokenize('   '));
        $this->assertSame([], LibrarySemanticScorer::tokenize('le la les de'));
    }

    #[Test]
    public function score_est_nul_sans_correspondance(): void
    {
        $score = LibrarySemanticScorer::score(
            ['title' => 'Comptabilité générale', 'description' => 'Bilan et compte de résultat'],
            'jardinage',
        );

        $this->assertSame(0.0, $score);
    }

    #[Test]
    public function score_est_nul_pour_une_requete_vide(): void
    {
        $this->assertSame(0.0, LibrarySemanticScorer::score(['title' => 'Comptabilité'], ''));
    }

    #[Test]
    public function le_titre_pese_plus_que_la_description(): void
    {
        $inTitle = LibrarySemanticScorer::score(['title' => 'Gestion de stock'], 'stock');
        $inDescription = LibrarySemanticScorer::score(['description' => 'Gestion de stock'], 'stock');

        $this->assertGreaterThan($inDescription, $inTitle);
    }

    #[Test]
    public function un_document_couvrant_tous_les_mots_score_plus_haut(): void
    {
        $all = LibrarySemanticScorer::score(['title' => 'Gestion de stock et inventaire'], 'gestion stock');
        $partial = LibrarySemanticScorer::score(['title' => 'Gestion des équipes'], 'gestion stock');

        $this->assertGreaterThan($partial, $all);
    }

    #[Test]
    public function la_correspondance_exacte_est_bonifiee(): void
    {
        $exact = LibrarySemanticScorer::score(['title' => 'Gestion de stock'], 'gestion de stock');
        $scattered = LibrarySemanticScorer::score(['title' => 'Stock : la gestion au quotidien'], 'gestion de stock');

        $this->assertGreaterThan($scattered, $exact);
    }

    #[Test]
    public function rank_exclut_les_documents_sans_correspondance(): void
    {
        $documents = [
            ['title' => 'Comptabilité générale'],
            ['title' => 'Gestion de stock'],
            ['title' => 'Jardinage urbain'],
        ];

        $ranked = LibrarySemanticScorer::rank(
            $documents,
            'stock',
            static fn (array $doc): array => $doc,
        );

        $this->assertCount(1, $ranked);
        $this->assertSame('Gestion de stock', $ranked[0]['item']['title']);
    }

    #[Test]
    public function rank_trie_par_score_decroissant(): void
    {
        $documents = [
            ['title' => 'Notions de stock', 'description' => ''],
            ['title' => 'Gestion de stock et inventaire', 'description' => 'Méthode complète'],
            ['title' => 'Divers', 'description' => 'Un chapitre sur le stock'],
        ];

        $ranked = LibrarySemanticScorer::rank(
            $documents,
            'gestion stock',
            static fn (array $doc): array => $doc,
        );

        $this->assertSame('Gestion de stock et inventaire', $ranked[0]['item']['title']);
        $this->assertGreaterThanOrEqual($ranked[1]['score'], $ranked[0]['score']);
    }

    #[Test]
    public function rank_est_stable_a_score_egal(): void
    {
        $documents = [
            ['title' => 'Stock A'],
            ['title' => 'Stock B'],
            ['title' => 'Stock C'],
        ];

        $ranked = LibrarySemanticScorer::rank(
            $documents,
            'stock',
            static fn (array $doc): array => $doc,
        );

        $this->assertSame(
            ['Stock A', 'Stock B', 'Stock C'],
            array_map(static fn (array $entry): string => $entry['item']['title'], $ranked),
        );
    }

    #[Test]
    public function rank_retourne_un_tableau_vide_si_rien_ne_correspond(): void
    {
        $ranked = LibrarySemanticScorer::rank(
            [['title' => 'Comptabilité']],
            'jardinage',
            static fn (array $doc): array => $doc,
        );

        $this->assertSame([], $ranked);
    }

    #[Test]
    public function le_score_est_borne_et_positif(): void
    {
        $score = LibrarySemanticScorer::score(
            [
                'title' => 'Gestion de stock',
                'excerpt' => 'Gestion de stock',
                'description' => 'Gestion de stock',
                'rights_holder' => 'Gestion de stock',
                'publisher' => 'Gestion de stock',
                'category' => 'Gestion de stock',
            ],
            'gestion de stock',
        );

        $this->assertGreaterThan(0.0, $score);
        $this->assertIsFloat($score);
    }
}
