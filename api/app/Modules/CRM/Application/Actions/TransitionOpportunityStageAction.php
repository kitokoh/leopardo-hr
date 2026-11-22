<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Enums\CrmOpportunityStage;
use App\Modules\CRM\Domain\Models\CrmActivity;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use Illuminate\Database\DatabaseManager;

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
 * (`CrmAutomationTrigger::OPPORTUNITY_STAGE_CHANGED`) est declare dans
 * l'enum mais **aucun emetteur n'existe** dans ce module — son docblock dit
 * « emis par le socle V0 une fois merge ». Cette action ne le simule pas :
 * brancher un faux emetteur donnerait l'illusion que les automatisations se
 * declenchent. Un `opened`/`closed` supplementaire sans consommateur ne
 * servirait a rien ; c'est un travail d'integration, pas une ligne ici.
 */
final class TransitionOpportunityStageAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array{stage: string, lost_reason?: string|null, amount?: mixed, expected_close_date?: string|null, notes?: string|null}  $data
     */
    public function execute(CrmOpportunity $opportunity, array $data, Employee $actor): CrmOpportunity
    {
        return $this->db->transaction(function () use ($opportunity, $data, $actor): CrmOpportunity {
            /** @var CrmOpportunity $locked */
            $locked = CrmOpportunity::query()->whereKey($opportunity->id)->lockForUpdate()->firstOrFail();

            $from = (string) $locked->stage;
            $target = CrmOpportunityStage::from((string) $data['stage']);
            $to = $target->value;

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
                $entry = sprintf('[%s] %s -> %s (%s)', now()->toIso8601String(), $from, $to, $actor->id);

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
