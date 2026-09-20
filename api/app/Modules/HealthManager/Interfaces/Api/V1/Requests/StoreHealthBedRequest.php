<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Models\HealthBed;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un lit — HC-002 (#7786). Code unique par tenant ; salle
 * parente du MÊME tenant (anti cross-tenant, fail-closed).
 */
class StoreHealthBedRequest extends FormRequest
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
            'room_id' => [
                'required',
                'integer',
                Rule::exists('health_rooms', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('health_beds', 'code')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'status' => ['nullable', Rule::in(HealthBed::STATUSES)],
        ];
    }
}
