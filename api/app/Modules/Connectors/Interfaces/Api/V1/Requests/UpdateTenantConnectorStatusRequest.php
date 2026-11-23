<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Interfaces\Api\V1\Requests;

use App\Modules\Connectors\Domain\Models\ConnectorConnection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #9848 (BC-14 INTEGRATION) — changement de statut d'une connexion tenant.
 */
class UpdateTenantConnectorStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(ConnectorConnection::STATUSES)],
        ];
    }
}
