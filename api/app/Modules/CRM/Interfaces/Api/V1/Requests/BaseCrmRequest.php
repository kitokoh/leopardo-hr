<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Issue #5711 — Base des requêtes CRM client.
 *
 * **Validation stricte** : tout champ inconnu présent dans le payload est
 * refusé (422 `unknown_fields`) — « Les entrées inconnues ... strictement
 * contrôlés ». Les Policies sont appliquées dans les controllers (jamais
 * de garde inline de remplacement).
 */
abstract class BaseCrmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * Surcharge compatible avec `FormRequest::validated($key = null, $default = null)`
     * (PHP 8.4 — une signature sans paramètres rend le chargement fatal).
     *
     * Le contrôle des champs inconnus n'est appliqué que sur l'appel complet
     * (`$key === null`) ; l'accès par clé (`validated('field')`) passe tel quel.
     *
     * @return mixed
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if ($key !== null) {
            return $validated;
        }

        $allowed = array_keys($this->rules());
        $unknown = array_values(array_diff(array_keys($this->all()), $allowed));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'unknown_fields' => ['Champs non autorisés : '.implode(', ', $unknown)],
            ]);
        }

        return $validated;
    }
}
