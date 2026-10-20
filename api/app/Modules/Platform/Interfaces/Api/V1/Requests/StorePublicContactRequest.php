<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Requests;

use App\Shared\Discovery\PublicProfileLocator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * #8760 — Demande de contact depuis un profil public (visiteur NON authentifié).
 *
 * Anti-spam : honeypot `company_website` (le contrôleur répond 201 factice SANS
 * persister) + `throttle:shop-public` sur la route. Consentement RGPD explicite
 * requis (`consent` accepted) — horodaté en base.
 *
 * `target_type` est restreint aux types RÉSOLUBLES (pas à tous les types de
 * découverte) : un type cherchable mais non joignable produirait un 422 opaque
 * au lieu de l'état « indisponible » que le frontend doit afficher.
 */
class StorePublicContactRequest extends FormRequest
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
            // Honeypot : absent du formulaire visible.
            'company_website' => ['nullable', 'string', 'max:500'],
            'target_type' => ['required', 'string', 'max:40', Rule::in($locator->types())],
            'target_slug' => ['required', 'string', 'max:160', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'sender_name' => ['required', 'string', 'min:2', 'max:120'],
            'sender_email' => ['required', 'email', 'max:255'],
            'sender_phone' => ['nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'consent' => ['required', 'accepted'],
            // Fournie par le client pour rendre le retry réseau idempotent.
            'idempotency_key' => ['required', 'string', 'min:8', 'max:80'],
        ];
    }
}
