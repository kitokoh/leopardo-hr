<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise a jour d'une offre d'emploi (#9732).
 *
 * Meme garde de tenant que la creation : `department_id` / `position_id` sont
 * verifies dans l'entreprise de l'acteur.
 */
final class UpdateRecruitmentJobRequest extends FormRequest
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
            'title' => 'sometimes|string|max:200',
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
            'skills_required' => 'nullable|array',
            'status' => 'sometimes|in:draft,published,closed,archived',
            'closes_at' => 'nullable|date',
        ];
    }
}
