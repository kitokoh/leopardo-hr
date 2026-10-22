<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Requests;

use App\Shared\Discovery\PublicProfileLocator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #8760 — Ouverture d'une conversation depuis un profil public.
 *
 * Le client ne fournit QUE des identifiants publics déjà affichés dans
 * `/decouvrir` : `target_type` + `target_slug`. Il ne peut donc pas viser un
 * tenant arbitraire — la résolution du professionnel reste côté serveur.
 *
 * `target_type` est restreint aux types RÉSOLUBLES (pas à tous les types de
 * découverte) : un type cherchable mais non joignable produirait un 422 opaque
 * au lieu de l'état « indisponible » que le frontend doit afficher.
 */
class StartPublicConversationRequest extends FormRequest
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
        /** @var PublicProfileLocator $locator */
        $locator = app(PublicProfileLocator::class);

        return [
            'target_type' => ['required', 'string', 'max:40', Rule::in($locator->types())],
            'target_slug' => ['required', 'string', 'max:160', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ];
    }
}
