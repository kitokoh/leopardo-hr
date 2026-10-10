<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Api\V1\Requests;

use App\Modules\Fundraising\Domain\Enums\PayoutMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation d'une demande de reversement (verticale FUNDRAISING —
 * spec §3.3). Le plafond exact (solde disponible) est vérifié en
 * transaction par RequestPayoutAction — ici, forme seulement.
 */
final class RequestPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RBAC : FundraisingPayoutPolicy@create (contrôleur)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            'method' => ['required', Rule::enum(PayoutMethod::class)],
            'recipient_name' => ['required', 'string', 'max:190'],
            'recipient_account' => ['required', 'string', 'max:190'],
        ];
    }
}
