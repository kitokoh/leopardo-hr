<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un lit (HC-002, #7786) — dont changement de statut
 * (libre/occupé/maintenance).
 */
class UpdateHealthBedRequest extends FormRequest
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
                'sometimes',
                'integer',
                Rule::exists('health_rooms', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'code' => ['sometimes', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(['free', 'occupied', 'maintenance'])],
        ];
    }
}
