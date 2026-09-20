<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Confirmation d'une commande en ligne Leopardo Marche
 * (BC-17 RETAIL, #7808).
 *
 * `location_id` optionnel : emplacement de preparation sur lequel le stock
 * est decremente (mouvements `sale`) — a defaut, l'emplacement rattache a
 * la commande au checkout. Doit exister DANS le tenant (Rule::exists scoped
 * company_id — pas de fuite cross-tenant). L'autorisation est tranchee par
 * RetailOrderPolicy::fulfill().
 */
class ConfirmRetailOnlineOrderRequest extends FormRequest
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
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('retail_locations', 'id')
                    ->where('company_id', (string) $actor->company_id),
            ],
        ];
    }
}
