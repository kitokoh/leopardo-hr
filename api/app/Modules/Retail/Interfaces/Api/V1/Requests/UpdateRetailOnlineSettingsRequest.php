<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mise a jour des reglages boutique en ligne du tenant (BC-17, #7808).
 *
 * Le slug public est unique GLOBALEMENT (identifiant cross-tenant de la
 * boutique — collision => 422). `location_id` doit appartenir au tenant
 * (Rule::exists scoped company_id, pas de fuite cross-tenant).
 */
class UpdateRetailOnlineSettingsRequest extends FormRequest
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
        /** @var Employee $actor */
        $actor = $this->user();

        return [
            'slug' => [
                'required',
                'string',
                'max:160',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('retail_online_settings', 'slug')
                    ->ignore((string) $actor->company_id, 'company_id'),
            ],
            'display_name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'enabled' => ['required', 'boolean'],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('retail_locations', 'id')
                    ->where('company_id', (string) $actor->company_id),
            ],
        ];
    }
}
