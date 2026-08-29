<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use App\Modules\CRM\Domain\Enums\CrmExportEntity;
use App\Modules\CRM\Infrastructure\Services\CrmExportColumns;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'un export CRM (issue #5729).
 *
 * Entrées strictes : entité whitelistée, colonnes limitées à l'allowlist,
 * filtres contrôlés (statut/owner uniquement), pas de champs inconnus.
 */
final class StoreCrmExportRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entity' => ['required', 'string', 'in:'.implode(',', CrmExportEntity::values())],
            'columns' => ['sometimes', 'array', 'max:30'],
            'columns.*' => ['string'],
            'filters' => ['sometimes', 'array'],
            'filters.status' => ['sometimes', 'string', 'max:40'],
            'filters.owner_id' => ['sometimes', 'string', 'max:64'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $entity = (string) ($this->input('entity') ?? '');
            foreach ($this->input('columns', []) as $column) {
                if (! CrmExportColumns::isValidColumn($entity, (string) $column)) {
                    $validator->errors()->add('columns', 'Colonne non allowlistée pour '.$entity.' : '.$column);

                    return;
                }
            }
        });
    }
}
