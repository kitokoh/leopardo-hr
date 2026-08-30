<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-904 (#6107) — Participation à un quiz.
 * Réponses : liste {question_id, selected_option} — la notation est serveur.
 */
class ParticipateTravelQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelQuizPolicy::participate() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'participant_email' => ['required', 'email:rfc', 'max:190'],
            'participant_name' => ['nullable', 'string', 'max:160'],
            'answers' => ['required', 'array', 'max:50'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.selected_option' => ['required', 'integer', 'min:0'],
        ];
    }
}
