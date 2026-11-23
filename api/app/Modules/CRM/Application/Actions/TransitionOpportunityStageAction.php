<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Enums\CrmAutomationTrigger;
use App\Modules\CRM\Domain\Enums\CrmOpportunityStage;
use App\Modules\CRM\Domain\Models\CrmActivity;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use App\Modules\CRM\Infrastructure\Services\AutomationEngine;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;

/**
 * Deplacement d'une opportunite dans le pipeline — #9780.
 *
 * ## Ce que cette action ne fait PAS
 *
 * Elle n'invente pas de regles de parcours : l'ordre `prospecting ->
 * qualification -> proposal -> negotiation` est INDICATIF (un client peut
 * revenir en arriere apres un echange). Seule la sortie d'un etat TERMINAL
 * (`won`/`lost`) est interdite, et c'est la requete qui la refuse.
 *
 * ## Ce qu'elle fait, et pourquoi chaque morceau compte
 *
 *  1. **Ecrit `stage` ET `status` ensemble.** Un `stage=won` avec
 *     `status=open` serait deux verites contradictoires dans la meme ligne, et
 *     le dashboard (qui filtre sur `status`) afficherait une affaire gagnee
 *     comme en cours.
 *  2. **Journalise l'etape PRECEDENTE dans `notes`, horodatee.** Rien d'autre
 *     ne conserve l'historique du pipeline : sans cette ligne, on sait ou
 *     l'affaire est, pas d'ou elle vient ni depuis quand.
 *  3. **Ecrit une `CrmActivity` de type `stage_changed`** quand la table
 *     existe : c'est la trace que la timeline du compte affiche.
 *
 * ## Ce qu'elle ne peut pas garantir (et qui doit etre dit)
 *
 * L'evenement d'automatisation `crm.opportunity.stage_changed`
 * (`CrmAutomationTrigger::OPPORTUNITY_STAGE_CHANGED`) est desormais EMIS
 * (cf. `emitStageChanged()`), mais l'emission est **best-effort** : une
 * automatisation qui echoue ne doit pas annuler un deplacement d'etape deja
 * commite. Le moteur lui-meme est idempotent (`run_key`), et la cle porte
 * l'etape cible pour qu'une affaire qui avance deux fois declenche bien deux
 * fois.
 */
final class TransitionOpportunityStageAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        // #9859 — la facade `Log` est reservee a Interfaces/Infrastructure
        // (issue #6568). La couche Application depend du contrat PSR-3.
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param  array{stage: string, lost_reason?: string|null, amount?: mixed, expected_close_date?: string|null, notes?: string|null}  $data
     */
    public function execute(CrmOpportunity $opportunity, array $data, Employee $actor): CrmOpportunity
    {
        /** @var array{from: string, to: string} $transition */
        $transition = ['from' => '', 'to' => ''];

        $updated = $this->db->transaction(function () use ($opportunity, $data, $actor, &$transition): CrmOpportunity {
            /** @var CrmOpportunity $locked */
            $locked = CrmOpportunity::query()->whereKey($opportunity->id)->lockForUpdate()->firstOrFail();

            $from = (string) $locked->stage;
            $target = CrmOpportunityStage::from((string) $data['stage']);
            $to = $target->value;

            $transition = ['from' => $from, 'to' => $to];

            // `status` est DERIVE de `stage` : une seule verite par ligne.
            // Les deux etats terminaux ferment l'affaire ; tous les autres la
            // maintiennent ouverte.
            $locked->stage = $to;
            $locked->status = in_array($target, [CrmOpportunityStage::Won, CrmOpportunityStage::Lost], true)
                ? 'closed'
                : 'open';

            if (isset($data['amount'])) {
                $locked->amount = $data['amount'];
            }

            if (array_key_exists('expected_close_date', $data)) {
                $locked->expected_close_date = $data['expected_close_date'];
            }

            if ($from !== $to) {
                // Journal append-only : chaque ligne raconte un deplacement.
                // On horodate, sinon un retour en arriere et un aller simple
                // seraient indistinguables.
                // La fleche est un caractere typographique (`→`), pas `->` :
                // c'est le contrat que la timeline et les tests de #9790
                // attendent, et `notes` est un texte lu par un humain.
                $entry = sprintf('[%s] %s → %s (%s)', now()->toIso8601String(), $from, $to, $actor->id);

                if (! empty($data['lost_reason'])) {
                    // La raison de la perte voyage AVEC le deplacement : c'est
                    // la seule information qui explique la ligne six mois plus
                    // tard, et `crm_opportunities` n'a pas de colonne dediee.
                    $entry .= ' — '.(string) $data['lost_reason'];
                }

                $locked->notes = trim(((string) $locked->notes)."\n".$entry);
            }

            if (! empty($data['notes'])) {
                $locked->notes = trim(((string) $locked->notes)."\n".(string) $data['notes']);
            }

            $locked->save();

            $this->recordActivity($from, $to, $locked, $actor);

            return $locked->refresh();
        });

        // Emission HORS transaction : l'evenement decrit un fait COMMITE, jamais
        // une intention. Emettre a l'interieur ferait tourner les automatisations
        // sur une transition qu'un rollback pourrait annuler — un email de
        // « affaire gagnee » pour une affaire qui n'a pas bouge. Best-effort
        // (cf. docblock de `emitStageChanged()`).
        if ($transition['from'] !== '' && $transition['from'] !== $transition['to']) {
            $this->emitStageChanged($transition['from'], $transition['to'], $updated, $actor);
        }

        return $updated;
    }

    /**
     * Emet `crm.opportunity.stage_changed` vers le moteur d'automatisations.
     *
     * ## Pourquoi ce n'est pas un faux emetteur
     *
     * `AutomationEngine::dispatch()` existe et est deja branche sur les
     * evenements canal (`CrmChannelService::dispatchAutomationEvent()`). Le
     * seul maillon manquant etait l'appel : l'enum declarait l'evenement, le
     * moteur savait le consommer, et personne ne le produisait. On appelle
     * donc le MEME moteur, avec le MEME contrat de contexte.
     *
     * ## Le piege de l'idempotence
     *
     * Le moteur dedoublonne par `run_key` = (automation, evenement, entite).
     * Pour une creation d'entite c'est correct : l'evenement n'arrive qu'une
     * fois. Pour un CHANGEMENT D'ETAPE, la meme opportunite declenche
     * l'evenement plusieurs fois dans sa vie (prospecting -> proposal -> won).
     * Sans discriminant, la deuxieme transition serait classee « deja
     * traitee » et l'automatisation ne tournerait qu'une fois par affaire.
     * `dedupe_key` porte donc l'etape CIBLE : deux transitions vers deux
     * etapes differentes sont deux executions, un rejeu de la meme transition
     * reste un no-op.
     *
     * ## Non bloquant, et pourquoi
     *
     * La transition est deja commitee quand on arrive ici. Une exception du
     * moteur (automatisation mal configuree, action en echec, arret d'urgence)
     * ne doit pas transformer un deplacement d'etape reussi en 500 : le
     * commercial perdrait son clic pour un probleme qui ne le concerne pas.
     * L'echec est journalise, jamais avale en silence.
     */
    private function emitStageChanged(string $from, string $to, CrmOpportunity $opportunity, Employee $actor): void
    {
        try {
            /** @var AutomationEngine $engine */
            $engine = app(AutomationEngine::class);

            $engine->dispatch(CrmAutomationTrigger::OPPORTUNITY_STAGE_CHANGED, [
                'entity_type' => 'opportunity',
                'entity_id' => (string) $opportunity->id,
                'dedupe_key' => $to,
                'data' => [
                    'opportunity_id' => (string) $opportunity->id,
                    'from_stage' => $from,
                    'to_stage' => $to,
                    'status' => (string) $opportunity->status,
                    'amount' => $opportunity->amount,
                    'actor_id' => (string) $actor->id,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('CRM automation: emission stage_changed ignoree (non bloquant)', [
                'opportunity_id' => $opportunity->id,
                'from' => $from,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Trace dans la timeline du compte — best-effort, jamais bloquant.
     *
     * La table `crm_activities` peut ne pas exister sur un tenant ancien : une
     * transition de pipeline ne doit pas echouer pour une ligne d'historique.
     * L'ecriture de `stage`/`status`, elle, est deja faite et commitee.
     */
    private function recordActivity(string $from, string $to, CrmOpportunity $opportunity, Employee $actor): void
    {
        if ($from === $to) {
            return;
        }

        try {
            // Colonnes reelles de `crm_activities` : `opportunity_id` porte le
            // lien (la table est append-only et n'a pas de couple
            // subject_type/subject_id), `occurred_at` porte la date METIER,
            // distincte de `created_at`.
            CrmActivity::query()->create([
                'company_id' => $opportunity->company_id,
                'opportunity_id' => $opportunity->id,
                'type' => CrmActivity::TYPE_STAGE_CHANGED,
                'subject' => __('crm.opportunities.activity_stage_changed'),
                'description' => sprintf('%s -> %s', $from, $to),
                'occurred_at' => now(),
                'created_by' => $actor->id,
            ]);
        } catch (\Throwable) {
            // Volontairement silencieux : cf. docblock. La transition est deja
            // persistee ; une timeline manquante n'invalide pas le pipeline.
        }
    }
}
