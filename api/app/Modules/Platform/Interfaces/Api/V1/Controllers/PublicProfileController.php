<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\User;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Domain\Models\PublicProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * COULOIR 16 — M3-bis · Profil public du client (schéma `public`).
 *
 * ## Pourquoi ces routes existent
 *
 * `PUBLIC_PROFILE_DATA_MODEL.md` §3 déclare `public_profiles` « à créer ». Sans
 * elle, la préférence de langue est écrite localement par la PWA et par l'app
 * Flutter : elle suit l'APPAREIL, pas la personne. Un client qui change de
 * téléphone perd sa langue.
 *
 * ## Ce que ces routes ne font pas
 *
 * Elles ne touchent ni au téléphone (`users.phone_e164`), ni à la position, ni
 * à l'identité. Elles ne créent aucun compte : le profil est créé à la première
 * lecture, pour un client **déjà authentifié** sous `user_api`.
 *
 * ## `preferred_locale` prime sur `users.preferred_language`
 *
 * `users.preferred_language` est en `varchar(2)` : il ne peut pas porter
 * `ar-SA`. Pour la surface publique, c'est `preferred_locale` qui fait foi. On
 * ne supprime pas l'ancien champ — on le laisse comme valeur par défaut.
 */
class PublicProfileController extends Controller
{
    /**
     * GET /api/v1/user/public-profile
     *
     * Crée le profil à la première lecture (idempotent) : un client qui n'a
     * jamais rien réglé obtient un profil par défaut, pas un 404. Un 404
     * obligerait chaque écran à traiter « pas encore de profil » comme une
     * erreur, alors que c'est l'état normal d'un nouveau client.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $client */
        $client = $request->user('user_api');

        return new JsonResponse([
            'data' => $this->format($this->profileFor($client)),
        ]);
    }

    /**
     * PATCH /api/v1/user/public-profile
     *
     * Met à jour la langue, la ville approximative et le consentement aux
     * notifications.
     *
     * ## Le consentement s'écrit avec sa date, jamais seul
     *
     * `notifications_opt_in = true` sans `notifications_consent_at` est un état
     * incohérent : la contrainte CHECK de la migration le refuserait de toute
     * façon (SQLSTATE 23514), mais le refuser ici donne un message exploitable
     * plutôt qu'une erreur SQL brute. Un retrait de consentement (`false`)
     * efface la date : on ne garde pas la preuve d'un consentement qui n'existe
     * plus.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var User $client */
        $client = $request->user('user_api');

        $validated = $request->validate([
            'preferred_locale' => ['sometimes', 'string', 'in:'.implode(',', PublicProfile::SUPPORTED_LOCALES)],
            'approx_city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'approx_country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'notifications_opt_in' => ['sometimes', 'boolean'],
            'privacy_policy_version' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $profile = $this->profileFor($client);

        if (array_key_exists('notifications_opt_in', $validated)) {
            $optIn = (bool) $validated['notifications_opt_in'];

            $validated['notifications_consent_at'] = $optIn ? now() : null;
        }

        $profile->fill($validated)->save();

        return new JsonResponse([
            'data' => $this->format($profile->refresh()),
        ]);
    }

    /**
     * Profil du client, créé à la première lecture.
     *
     * `firstOrCreate` et non `create` : deux requêtes simultanées du même
     * client (web + mobile) ne doivent pas produire une erreur de clé
     * primaire. La PK est `user_id`, donc la seconde écriture est un no-op.
     */
    private function profileFor(User $client): PublicProfile
    {
        $profile = PublicProfile::query()->firstOrCreate(
            ['user_id' => $client->id],
            [
                // Valeur par défaut reprise de `users.preferred_language`
                // quand elle est servie, sinon `fr` (défaut du produit).
                'preferred_locale' => $this->defaultLocaleFor($client),
            ],
        );

        // `firstOrCreate` ne relit pas les DÉFAUTS de la base : sur une ligne
        // fraîchement créée, `notifications_opt_in` vaudrait `null` en mémoire
        // alors que la colonne vaut `false`. On relit pour que la réponse
        // décrive l'état réellement stocké, pas l'état partiel de l'insert.
        return $profile->wasRecentlyCreated ? $profile->refresh() : $profile;
    }

    /**
     * Locale par défaut : celle du compte si elle est servie, sinon `fr`.
     *
     * On ne recopie pas `users.preferred_language` aveuglément : s'il contient
     * une valeur hors des 4 langues du couloir, on retombe sur `fr` plutôt que
     * de créer un profil dans une langue que la surface ne sait pas rendre.
     */
    private function defaultLocaleFor(User $client): string
    {
        $candidate = (string) ($client->preferred_language ?? '');

        return in_array($candidate, PublicProfile::SUPPORTED_LOCALES, true) ? $candidate : 'fr';
    }

    /**
     * @return array<string, mixed>
     */
    private function format(PublicProfile $profile): array
    {
        return [
            'preferred_locale' => $profile->preferred_locale,
            'approx_city' => $profile->approx_city,
            'approx_country' => $profile->approx_country,
            'notifications_opt_in' => $profile->notifications_opt_in,
            'notifications_consent_at' => $profile->notifications_consent_at?->toIso8601String(),
            'privacy_policy_version' => $profile->privacy_policy_version,
            'last_active_at' => $profile->last_active_at?->toIso8601String(),
            'supported_locales' => PublicProfile::SUPPORTED_LOCALES,
        ];
    }
}
