<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TRAVEL-914 (#6422) — Base de mise à jour d'un référentiel d'annonces.
 * Le code reste unique par tenant, en excluant l'enregistrement courant.
 */
abstract class UpdateTravelAdvertReferenceRequest extends FormRequest
{
    /** Nom du paramètre de route lié au modèle référentiel (ex. travelAdvertType). */
    abstract protected function referenceRouteParam(): string;

    abstract protected function referenceTable(): string;

    public function authorize(): bool
    {
        return true; // rôles gestion tranchés au controller (hasManagerRole)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user() instanceof Employee ? $this->user()->company_id : null;

        return [
            'code' => [
                'required', 'string', 'min:2', 'max:40', 'regex:/^[a-z0-9_]+$/',
                Rule::unique($this->referenceTable(), 'code')
                    ->where(fn (Builder $query) => $query->where('company_id', $companyId))
                    ->ignore($this->route($this->referenceRouteParam())),
            ],
            'label' => ['required', 'string', 'min:1', 'max:120'],
        ];
    }
}
