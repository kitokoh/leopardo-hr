<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Models\CrmOpportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Changement d'etape d'une opportunite — #9780.
 *
 * ## Le defaut que cette requete sert a corriger
 *
 * Une opportunite ne pouvait etre CREE que par `ConvertLeadAction`. Aucune
 * route ne changeait son `stage` : ni creation directe, ni deplacement dans
 * le kanban. Et `CrmOpportunityStage` n'etait jamais validee a l'ecriture sur
 * ce chemin — la colonne n'etait qu'un `string(80)` avec un defaut.
 *
 * Consequence concrete : un commercial suivait son pipeline... en lecture
 * seule. L'etape restait `prospecting` pour toujours sur les opportunites
 * converties en `prospecting`, et une opportunite gagnee ne pouvait pas etre
 * marquee `won`.
 *
 * ## Terminalite : `won` et `lost` sont definitifs
 *
 * Rouvrir une affaire gagnee ou perdue reecrirait l'histoire commerciale (le
 * CA deja compte, la raison de la perte). On les rend donc terminaux : pour
 * repartir, on cree une NOUVELLE opportunite, et l'ancienne garde sa trace.
 * Le refus est un 422 de validation explicite, pas un echec silencieux.
 *
 * `status` suit `stage` et n'est pas librement editable ici : laisser un
 * client ecrire `status=open` sur une opportunite `won` produirait deux
 * verites dans la meme ligne.
 */
final class TransitionOpportunityStageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage' => [
                'required',
                'string',
                Rule::in(['prospecting', 'qualification', 'proposal', 'negotiation', 'won', 'lost']),
            ],
            // Obligatoire pour `lost` : une affaire perdue sans raison ecrite
            // est inexploitable au bilan trimestriel.
            //
            // Et INTERDIT ailleurs : un motif de perte sur une affaire gagnee
            // (ou en cours) est une donnee trompeuse — il serait stocke dans
            // `notes` a cote d'un `stage=won` et lirait comme une perte. On le
            // refuse explicitement plutot que de l'ignorer en silence, sinon
            // l'appelant croit avoir enregistre quelque chose.
            'lost_reason' => [
                'required_if:stage,lost',
                'prohibited_unless:stage,lost',
                'nullable',
                'string',
                'max:500',
            ],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stage.in' => __('crm.opportunities.stage_invalid'),
            'lost_reason.required_if' => __('crm.opportunities.lost_reason_required'),
        ];
    }

    /**
     * Deux verrous, exprimes apres les regles de forme :
     *
     *  1. **tenant** — l'opportunite doit appartenir au tenant courant. Le
     *     model binding passe par `BelongsToCompany`, donc un id d'une autre
     *     entreprise est deja introuvable ; on ne s'appuie pas uniquement
     *     la-dessus en ecriture (defense en profondeur).
     *  2. **terminalite** — pas de sortie de `won` / `lost`.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            /** @var CrmOpportunity|null $opportunity */
            $opportunity = $this->route('opportunity');

            if (! $opportunity instanceof CrmOpportunity) {
                return;
            }

            $current = $opportunity->stage;

            if (in_array($current, ['won', 'lost'], true) && $this->input('stage') !== $current) {
                $validator->errors()->add('stage', __('crm.opportunities.stage_terminal'));

                return;
            }

            // Avancer sans changer, ou revenir en arriere dans le parcours
            // ouvert (prospecting -> qualification -> proposal ->
            // negotiation) est legitime : un client peut revenir a l'etape
            // precedente apres un echange. On n'interdit donc QUE la sortie
            // d'un etat terminal.
            if ($this->input('stage') === $current) {
                return;
            }
        });
    }
}
