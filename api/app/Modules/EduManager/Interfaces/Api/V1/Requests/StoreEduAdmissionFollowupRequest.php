<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Requests;

use App\Modules\EduManager\Domain\Models\EduAdmissionFollowup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Relance marketing d'admission (EDU-015, #5831).
 * Consommation consentie uniquement (service : EDU_CONSENT_REQUIRED) ;
 * idempotence par (admission, campaign_code, channel).
 */
class StoreEduAdmissionFollowupRequest extends FormRequest
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
            'campaign_code' => ['required', 'string', 'max:80'],
            'channel' => ['required', Rule::in(EduAdmissionFollowup::CHANNELS)],
            'sent_at' => ['nullable', 'date'],
        ];
    }
}
