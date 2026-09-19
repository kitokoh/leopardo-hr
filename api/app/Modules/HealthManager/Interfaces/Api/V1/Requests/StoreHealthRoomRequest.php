<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une salle (HC-002, #7786). Code unique par tenant ; le service
 * référencé doit appartenir au MÊME tenant (exists scopé company_id).
 */
class StoreHealthRoomRequest extends FormRequest
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
            'department_id' => [
                'required',
                'integer',
                Rule::exists('health_departments', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('health_rooms', 'code')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'name' => ['required', 'string', 'max:191'],
            'room_type' => ['nullable', Rule::in(['consultation', 'hospitalization', 'surgery', 'emergency', 'other'])],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
        ];
    }
}
