<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une facture de soins (HC-007, #7791) : patient du MÊME tenant
 * (422 sinon), AU MOINS UNE ligne d'acte, chaque acte du catalogue du
 * tenant et ACTIF. Les prix ne sont JAMAIS acceptés depuis la requête :
 * ils sont FIGÉS depuis le catalogue côté serveur ; le total est recalculé
 * serveur (Σ lignes − remise).
 */
class StoreHealthInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee|null $actor */
        $actor = $this->user();

        return [
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('health_patients', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'discount' => ['sometimes', 'numeric', 'min:0', 'max:9999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.care_act_id' => [
                'required',
                'integer',
                Rule::exists('health_care_acts', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('company_id', $actor?->company_id)
                        ->where('is_active', true)
                ),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
