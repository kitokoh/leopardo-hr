<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * IAM-L2 (#9092) — corps de `POST /api/v1/auth/context`.
 *
 * `company_id` est requis : la sélection est **explicite**. Aucun défaut
 * implicite n'est accepté — c'est précisément le contexte arbitraire que ce
 * lot supprime.
 */
class SelectTenantContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'string', 'max:64'],
        ];
    }
}
