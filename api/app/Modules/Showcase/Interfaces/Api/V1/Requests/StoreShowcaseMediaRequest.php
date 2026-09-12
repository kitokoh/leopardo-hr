<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Requests;

use App\Modules\Showcase\Domain\Enums\ShowcaseMediaKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * BC-27 SHOWCASE (V-MEDIA #6872) — validation de l'upload d'un média.
 *
 * Le type (`kind`, obligatoire) détermine les extensions autorisées et la
 * limite de poids : logo (png/jpg/jpeg/webp/svg, 2 Mo) ou image de section
 * (png/jpg/jpeg/webp, 5 Mo). `section_id` est optionnel ici et sa cohérence
 * (obligatoire pour une image de section, interdit pour le logo, section
 * existante de la vitrine) est vérifiée par
 * {@see \App\Modules\Showcase\Application\Actions\UploadShowcaseMediaAction}
 * (422 avec messages du catalogue `showcase.*`).
 */
final class StoreShowcaseMediaRequest extends FormRequest
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
        $kind = $this->kind();

        return [
            'kind' => ['required', 'string', Rule::in(ShowcaseMediaKind::values())],
            'section_id' => ['nullable', 'integer', 'min:1'],
            'file' => [
                'required',
                'file',
                'max:'.$kind->maxKilobytes(),
                'mimes:'.implode(',', $kind->allowedExtensions()),
            ],
        ];
    }

    /**
     * Messages localisés (catalogue `api/lang/*\/showcase.php`, garde PA2-I18N-007).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $kind = $this->kind();

        return [
            'file.mimes' => __('showcase.media_type_not_allowed', [
                'kind' => $kind->value,
                'types' => implode(', ', $kind->allowedExtensions()),
            ]),
            'file.max' => __('showcase.media_too_large', [
                'max' => (string) $kind->maxKilobytes(),
            ]),
        ];
    }

    /**
     * Type demandé, borné à l'image de section si la valeur est absente ou
     * inconnue (la règle `Rule::in` produit alors le 422 sur `kind`).
     */
    private function kind(): ShowcaseMediaKind
    {
        return ShowcaseMediaKind::tryFrom((string) $this->input('kind')) ?? ShowcaseMediaKind::Section;
    }
}
