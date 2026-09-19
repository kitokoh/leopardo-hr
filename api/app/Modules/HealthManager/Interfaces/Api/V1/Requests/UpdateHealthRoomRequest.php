<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HealthManager\Domain\Models\HealthRoom;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'une salle — HC-002 (#7786).
 */
class UpdateHealthRoomRequest extends FormRequest
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

        /** @var HealthRoom|null $room */
        $room = $this->route('room');

        return [
            'department_id' => [
                'sometimes',
                'integer',
                Rule::exists('health_departments', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'name' => ['sometimes', 'string', 'max:150'],
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('health_rooms', 'code')
                    ->ignore($room?->getAttribute('id'))
                    ->where(
                        fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                    ),
            ],
            'type' => ['sometimes', Rule::in(HealthRoom::TYPES)],
            'status' => ['sometimes', Rule::in(HealthRoom::STATUSES)],
        ];
    }
}
