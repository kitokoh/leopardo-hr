<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\GuardianAccessToken;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Émission d'un lien d'accès expirable pour un responsable légal (EDU-013).
 *
 * Direction uniquement ; la durée est bornée (1..30 jours, défaut 7).
 */
class IssueGuardianAccessLinkRequest extends FormRequest
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
            'guardian_id' => [
                'required',
                'integer',
                Rule::exists('edu_guardians', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:'.GuardianAccessToken::MAX_TTL_DAYS],
        ];
    }
}
