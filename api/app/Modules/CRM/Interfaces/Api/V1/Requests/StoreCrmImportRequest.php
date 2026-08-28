<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #5714 — Validation stricte de l'upload CSV (ADR-CRM-005).
 *
 * Bornes contrôlées dès l'entrée : entité whitelistée, fichier requis,
 * extension .csv/.txt, taille plafonnée (2 Mo — cohérent avec
 * CsvParser::MAX_FILE_SIZE_BYTES). Le parsing structurel (encodage,
 * colonnes, lignes) est assuré ensuite par le moteur d'import.
 */
class StoreCrmImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la Policy (CrmImportPolicy) tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'in:accounts,contacts,leads'],
            'file' => [
                'required',
                'file',
                'max:2048',
                'mimes:csv,txt',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entity_type.required' => 'L\'entité à importer est requise (accounts, contacts ou leads).',
            'entity_type.in' => 'Entité inconnue : autorisé accounts, contacts ou leads.',
            'file.required' => 'Un fichier CSV est requis.',
            'file.file' => 'Le fichier est invalide.',
            'file.max' => 'Le fichier dépasse la taille maximale de 2 Mo.',
            'file.mimes' => 'Extension non autorisée (CSV ou TXT attendu).',
        ];
    }
}
