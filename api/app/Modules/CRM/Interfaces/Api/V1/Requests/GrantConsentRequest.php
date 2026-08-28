<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Enums\ConsentChannel;
use App\Modules\CRM\Domain\Enums\ConsentPurpose;
use App\Modules\CRM\Domain\Enums\ConsentSource;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Accord / refus d'un consentement CRM — Issue #5722.
 *
 * Champs inconnus refusés à la volée par la règle `prohibited` (liste
 * d'autorisation stricte, convention CRM) ; l'autorisation RBAC est portée
 * par la Policy `CrmConsentPolicy` appelée dans le contrôleur.
 */
class GrantConsentRequest extends FormRequest
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
            'action' => ['required', 'string', 'in:granted,denied'],
            'contact_id' => ['required', 'integer', 'min:1'],
            'channel' => ['required', 'string', 'in:'.implode(',', ConsentChannel::values())],
            'purpose' => ['required', 'string', 'in:'.implode(',', ConsentPurpose::values())],
            'source' => ['required', 'string', 'in:'.implode(',', ConsentSource::values())],
            'source_ref' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'metadata.*' => ['nullable', 'scalar'],
            'id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
