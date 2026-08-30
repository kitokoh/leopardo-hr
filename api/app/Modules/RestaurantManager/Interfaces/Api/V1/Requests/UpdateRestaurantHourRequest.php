<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * RESTO-304 (#6185) — Validation stricte de modification d'un horaire restaurant.
 *
 * Règles applicatives (après validation de base) : passer `is_closed: false`
 * exige `opens_at` et `closes_at` ; que `is_closed` soit fourni ou non, la
 * fermeture (si présente) doit être strictement postérieure à l'ouverture
 * (si présente) — même sémantique que la règle native `after:opens_at`,
 * appliquée ici à des heures `H:i` sans date. Un jour fermé (`is_closed`
 * true) ignore les heures.
 */
class UpdateRestaurantHourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantHourPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->companyId();

        return [
            'branch_id' => [
                'sometimes',
                'integer',
                'min:1',
                Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'day_of_week' => ['sometimes', 'integer', 'min:0', 'max:6'],
            'opens_at' => ['nullable', 'date_format:H:i'],
            'closes_at' => ['nullable', 'date_format:H:i'],
            'is_closed' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $isClosed = $this->has('is_closed') && $this->boolean('is_closed');

            if ($isClosed) {
                return;
            }

            if ($this->has('is_closed')) {
                if (! $this->filled('opens_at')) {
                    $validator->errors()->add('opens_at', __('validation.required', ['attribute' => 'opens_at']));
                }

                if (! $this->filled('closes_at')) {
                    $validator->errors()->add('closes_at', __('validation.required', ['attribute' => 'closes_at']));
                }
            }

            $opensTimestamp = is_string($this->input('opens_at')) ? strtotime((string) $this->input('opens_at')) : false;
            $closesTimestamp = is_string($this->input('closes_at')) ? strtotime((string) $this->input('closes_at')) : false;

            if ($opensTimestamp !== false && $closesTimestamp !== false && $closesTimestamp <= $opensTimestamp) {
                $validator->errors()->add('closes_at', __('validation.after', [
                    'attribute' => 'closes_at',
                    'date' => (string) $this->input('opens_at'),
                ]));
            }
        });
    }

    /** Compagnie de l'acteur courant (pattern #3065/#3428 — scope compagnie sur les FK et uniques). */
    private function companyId(): ?string
    {
        $user = $this->user();
        if ($user instanceof Employee && $user->company_id !== null) {
            return $user->company_id;
        }

        return app()->bound('current_company') ? currentCompany()->id : null;
    }
}
