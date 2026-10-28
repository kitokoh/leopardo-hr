<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-909 (#6112) — Mise à jour d'un site touristique.
 *
 * Le contrôleur `update()` réutilisait `StoreTravelTouristSiteRequest`, où
 * `name` est **obligatoire**. Conséquence mesurée (#8396) : un PUT partiel
 * — publier/désactiver un site, corriger une seule coordonnée — répondait
 * `422 VALIDATION_ERROR` (« Le champ name est obligatoire »), donc **aucune
 * transition de statut n'était possible par l'API**. C'est exactement le
 * défaut corrigé sur `TravelQuiz` (même verticale, même cause).
 *
 * Le statut est borné aux valeurs de `TravelRecordStatus` (`active` |
 * `disabled`), source de vérité unique. `published`/`archived` — utilisés par
 * les tests — n'existent pas dans l'enum et faisaient lever un `ValueError`.
 */
class UpdateTravelTouristSiteRequest extends StoreTravelTouristSiteRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // Mise à jour partielle : `name` n'est requis que s'il est fourni.
        $rules['name'] = ['sometimes', 'required', 'string', 'min:1', 'max:160'];

        // Aligner les statuts acceptés sur l'enum (active|disabled).
        $rules['status'] = [
            'nullable',
            'string',
            Rule::in(array_column(TravelRecordStatus::cases(), 'value')),
        ];

        return $rules;
    }
}
