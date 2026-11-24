<?php

declare(strict_types=1);

namespace Tests\Unit\CRM;

use App\Modules\CRM\Domain\Enums\CrmOpportunityStage;
use Tests\TestCase;

/**
 * Issue #9886 — un seul separateur de transition, partage par les deux ecrivains.
 *
 * ## Le defaut que ce test verrouille
 *
 * `TransitionOpportunityStageAction` ecrivait la transition a DEUX endroits :
 *
 *   - `crm_opportunities.notes`  -> `sprintf('[%s] %s -> %s (%s)', ...)`
 *   - `crm_activities.description` -> `sprintf('%s -> %s', ...)`
 *
 * ... alors que le contrat attendu (et le test de feature) utilise la fleche
 * `→` (U+2192). Resultat : `notes` et la timeline racontaient le meme fait
 * avec deux separateurs differents, et `LIKE '%→%'` ne retrouvait que la
 * moitie des lignes d'historique.
 *
 * Ce test ne verifie pas « une chaine » : il verifie que la source unique de
 * verite existe et qu'elle est bien celle que les deux ecrivains consomment.
 * Si quelqu'un reecrit un `sprintf('%s -> %s')` en dur dans l'action, le test
 * de feature `CrmOpportunityStageTest` echoue ; si quelqu'un change la
 * constante, ce test-ci echoue. Les deux ensemble rendent la divergence
 * impossible.
 */
class CrmOpportunityStageTransitionFormatTest extends TestCase
{
    public function test_the_separator_is_the_canonical_arrow(): void
    {
        $this->assertSame('→', CrmOpportunityStage::TRANSITION_SEPARATOR);
    }

    public function test_format_transition_produces_the_expected_journal_entry(): void
    {
        $this->assertSame(
            'prospecting → proposal',
            CrmOpportunityStage::formatTransition('prospecting', 'proposal'),
        );
    }

    /**
     * Le separateur ne doit pas etre l'ASCII `->` : c'est precisement la
     * divergence qui a produit le bug. Un test qui accepterait les deux ne
     * protegerait rien.
     */
    public function test_the_separator_is_not_the_ascii_arrow(): void
    {
        $this->assertStringNotContainsString('->', CrmOpportunityStage::formatTransition('won', 'lost'));
    }

    /**
     * Les deux etapes terminales doivent se formater comme les autres : le
     * journal d'une perte porte le motif, pas une mise en forme speciale.
     */
    public function test_terminal_stages_use_the_same_separator(): void
    {
        $this->assertSame('negotiation → won', CrmOpportunityStage::formatTransition('negotiation', 'won'));
        $this->assertSame('proposal → lost', CrmOpportunityStage::formatTransition('proposal', 'lost'));
    }
}
