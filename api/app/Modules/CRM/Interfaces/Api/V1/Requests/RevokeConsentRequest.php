<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Enums\ConsentSource;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Retrait d'un consentement CRM — Issue #5722.
 *
 * Le retrait est tracé (audit_logs) et propage l'événement
 * `CrmConsentRevoked` (annulation des envois de campagne en attente).
 */
class RevokeConsentRequest extends FormRequest
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
            'source' => ['required', 'string', 'in:'.implode(',', ConsentSource::values())],
            'source_ref' => ['nullable', 'string', 'max:255'],
        ];
    }
}
