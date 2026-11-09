<?php

declare(strict_types=1);

namespace App\Modules\Library\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Enregistrement de la progression de lecture (surface tenant authentifiée).
 *
 * ⚠️ `employee_id` et `company_id` ne sont PAS acceptés dans le payload : ils
 * viennent de la session authentifiée. Les accepter permettrait d'écrire la
 * progression d'un autre employé (ou d'un autre tenant).
 */
class StoreLibraryReadingProgressRequest extends FormRequest
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
        return [
            'page' => ['required', 'integer', 'min:1', 'max:1000000'],
            'total_pages' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
