<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres de la liste d'audit (GET /api/v1/audit-logs).
 *
 * La permission (`viewTenantAudit`) reste portée par le contrôleur : elle
 * s'appuie sur un gate qui produit un message d'erreur métier explicite
 * (`auth.audit_access_principal_only`), qu'un simple `false` dans
 * `authorize()` remplacerait par un 403 générique.
 */
class AuditLogIndexRequest extends FormRequest
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'actor' => ['sometimes', 'integer', 'exists:employees,id'],
            'module' => ['sometimes', 'string', 'max:100'],
            'action' => ['sometimes', 'string', 'max:200'],
            'resource_type' => ['sometimes', 'string', 'max:200'],
            'resource_id' => ['sometimes', 'integer'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
        ];
    }
}
