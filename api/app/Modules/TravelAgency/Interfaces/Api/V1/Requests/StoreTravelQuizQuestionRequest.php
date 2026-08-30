<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TRAVEL-904 (#6107) — Ajout d'une question à un quiz.
 * `correct_option_index` doit pointer dans `options` (validation croisée).
 */
class StoreTravelQuizQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TravelQuizPolicy::update() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:1', 'max:500'],
            'options' => ['required', 'array', 'min:2', 'max:10'],
            'options.*' => ['required', 'string', 'max:200'],
            'correct_option_index' => ['required', 'integer', 'min:0'],
            'points' => ['nullable', 'integer', 'min:1', 'max:100'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $index = (int) $this->input('correct_option_index');
            $count = is_array($this->input('options')) ? count($this->input('options')) : 0;

            if ($index >= $count) {
                $validator->errors()->add('correct_option_index', 'L\'index de la bonne réponse doit pointer dans options.');
            }
        });
    }
}
