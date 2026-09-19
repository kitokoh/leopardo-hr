<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creation d'une commande de vente POS Retail (BC-17 RETAIL, #7674).
 *
 * Session et produits doivent exister DANS le tenant (Rule::exists scoped
 * company_id — pas de fuite cross-tenant). Au moins une ligne ; quantite
 * strictement positive, 3 decimales max. Les prix ne sont JAMAIS acceptes
 * du client : snapshots serveur (RetailPosService).
 */
class StoreRetailOrderRequest extends FormRequest
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
            'pos_session_id' => [
                'required',
                'integer',
                Rule::exists('retail_pos_sessions', 'id')
                    ->where('company_id', (string) $actor->company_id),
            ],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => [
                'required',
                'integer',
                Rule::exists('retail_products', 'id')
                    ->where('company_id', (string) $actor->company_id),
            ],
            'lines.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
                'regex:/^\d{1,9}(\.\d{1,3})?$/',
            ],
            'note' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
