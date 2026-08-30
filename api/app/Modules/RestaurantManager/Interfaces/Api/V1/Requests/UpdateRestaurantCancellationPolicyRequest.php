<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-603 (#6208) — Configuration de la politique d'annulation d'une branche.
 */
class UpdateRestaurantCancellationPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantBranchPolicy::update() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $actor */
        $actor = $this->user();

        return [
            'cancel_free_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'cancel_fee_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'branch_id' => ['prohibited', Rule::exists('restaurant_branches', 'id')->where(fn ($q) => $q->where('company_id', $actor->company_id))],
        ];
    }
}
