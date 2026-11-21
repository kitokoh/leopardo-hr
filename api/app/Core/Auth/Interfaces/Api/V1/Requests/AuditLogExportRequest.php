<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paramètres de l'export CSV d'audit (GET /api/v1/audit-logs/export).
 *
 * La permission reste vérifiée dans le contrôleur (gate `viewTenantAudit`
 * avec message métier).
 */
class AuditLogExportRequest extends FormRequest
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
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'action' => ['sometimes', 'string', 'max:200'],
        ];
    }
}
