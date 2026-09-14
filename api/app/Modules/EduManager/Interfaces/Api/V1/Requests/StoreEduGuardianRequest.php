<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un responsable légal (parent / tuteur) — EDU-002 (#5818).
 *
 * Le tenant est TOUJOURS celui de l'acteur : aucun `company_id` accepté depuis
 * la requête (isolation fail-closed). `contact_reference` (téléphone/email de
 * contact) est une PII chiffrée au repos — voir le cast `encrypted` du modèle.
 */
class StoreEduGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorisation portée par la policy (EduGuardianPolicy::create)
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Employee|null $actor */
        $actor = $this->user();

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'contact_reference' => ['nullable', 'string', 'max:191'],
            'relationship_code' => ['required', Rule::in(EduGuardian::RELATIONSHIPS)],
            // Rattachement facultatif à un employé du tenant (le responsable
            // légal peut être un membre du personnel : portail self-service).
            'employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
        ];
    }
}
