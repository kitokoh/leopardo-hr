<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Modules\TravelAgency\Domain\Enums\QuizStatus;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-914 (#6422) — Mise à jour d'un quiz (gestion admin).
 *
 * Mêmes règles que la création, mais **partielle** : un PUT ne portant que
 * `status` (publication d'un brouillon) est légitime et ne doit pas exiger
 * `title`. L'héritage direct de `StoreTravelQuizRequest` le rendait
 * obligatoire → 422 systématique sur toute transition de statut (#8396).
 *
 * Le statut est borné aux valeurs de `QuizStatus` (source de vérité) :
 * `published` — et non `active`, qui n'existe pas dans l'enum et n'était
 * donc jamais produit par le modèle casté.
 */
class UpdateTravelQuizRequest extends StoreTravelQuizRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // Mise à jour partielle : `title` n'est requis que s'il est fourni.
        $rules['title'] = ['sometimes', 'required', 'string', 'min:1', 'max:160'];

        // Aligner les statuts acceptés sur l'enum (draft|published|closed).
        $rules['status'] = ['nullable', 'string', Rule::in(array_column(QuizStatus::cases(), 'value'))];

        return $rules;
    }
}
