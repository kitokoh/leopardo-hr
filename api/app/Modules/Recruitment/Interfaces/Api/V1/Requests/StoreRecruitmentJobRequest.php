<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creation d'une offre d'emploi (#9732).
 *
 * `department_id` et `position_id` sont verifies **dans le tenant de l'acteur** :
 * un `exists:departments,id` nu accepterait l'identifiant d'un departement d'une
 * autre entreprise, et l'offre pointerait alors une structure etrangere.
 */
final class StoreRecruitmentJobRequest extends FormRequest
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
        /** @var Employee|null $user */
        $user = $this->user();
        $companyId = $user?->company_id;

        return [
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('company_id', $companyId),
            ],
            'position_id' => [
                'nullable',
                'integer',
                Rule::exists('positions', 'id')->where('company_id', $companyId),
            ],
            'location' => 'nullable|string|max:200',
            'remote_policy' => 'nullable|in:onsite,hybrid,remote',
            'contract_type' => 'nullable|in:cdi,cdd,stage,freelance',
            'salary_range_min' => 'nullable|numeric|min:0',
            'salary_range_max' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'skills_required' => 'nullable|array',
            'closes_at' => 'nullable|date',
        ];
    }
}
