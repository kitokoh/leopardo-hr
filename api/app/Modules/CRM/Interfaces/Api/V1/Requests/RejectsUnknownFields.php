<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Contracts\Validation\Validator;

/**
 * Rejet des champs inconnus sur les commandes mutantes — Issue #5711
 * (CRM-V0-07).
 *
 * Laravel ignore silencieusement les clés absentes de `rules()` : une
 * faute de frappe (`firs_name`) ou un champ non publié serait avalé sans
 * erreur. Ce trait transforme toute clé inconnue en erreur de validation
 * `_unknown` (422), conformément au critère « Champs inconnus refusés sur
 * commandes mutantes ».
 */
trait RejectsUnknownFields
{
    /**
     * Ajoute une erreur `_unknown` pour toute clé absente de `rules()`.
     */
    protected function rejectUnknownFields(Validator $validator): void
    {
        $allowed = array_keys($this->rules());

        $unknown = array_diff(array_keys($this->all()), $allowed);

        if ($unknown !== []) {
            $validator->errors()->add('_unknown', 'Champs inconnus : '.implode(', ', $unknown));
        }
    }
}
