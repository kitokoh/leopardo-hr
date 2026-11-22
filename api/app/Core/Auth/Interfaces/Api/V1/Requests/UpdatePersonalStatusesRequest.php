<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Statuts personnels de l'utilisateur (#9732).
 *
 * `present` (et non `required`) autorise le reset a vide (#5540) : un
 * utilisateur doit pouvoir retirer tous ses statuts.
 */
final class UpdatePersonalStatusesRequest extends FormRequest
{
    /** Valeurs admises (#5540). */
    public const VALID_STATUSES = [
        'student',
        'employee',
        'entrepreneur',
        'seeking_employment',
    ];

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
            'statuses' => ['present', 'array', 'max:4'],
            'statuses.*' => ['string', 'in:'.implode(',', self::VALID_STATUSES)],
        ];
    }
}
