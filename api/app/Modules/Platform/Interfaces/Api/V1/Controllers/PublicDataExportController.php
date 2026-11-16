<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\User;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Domain\Models\PublicActivityIndex;
use App\Modules\Platform\Domain\Models\PublicFavorite;
use App\Modules\Platform\Domain\Models\PublicProfile;
use App\Modules\Platform\Domain\Models\PublicSearchHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * COULOIR 16 — M3-bis · Export des données du client (portabilité RGPD).
 *
 * ## Pourquoi cette route existe
 *
 * L'écran Profil de la PWA affiche « bientôt disponible » pour l'export : il
 * ne ment pas, mais l'exigence n'est pas servie. Le RGPD (art. 20,
 * portabilité) impose de rendre au client les données qu'il a fournies, dans
 * un format structuré et lisible par machine.
 *
 * ## Ce que l'export contient — et ce qu'il ne contient pas
 *
 * Il contient **les données du client** : son compte (identité de contact), son
 * profil public, ses favoris, son historique de recherche, ses références
 * d'activité et ses jetons d'appareil (sans le secret du jeton).
 *
 * Il ne contient **aucune donnée d'un commerçant** : ni commande, ni
 * réservation, ni message. Ces données appartiennent au professionnel et
 * relèvent de son propre export — les inclure ici les divulguerait à un tiers
 * qui n'en est pas le responsable de traitement.
 *
 * ## Pourquoi les jetons d'appareil sont tronqués
 *
 * Un jeton d'appareil est un **secret de transport** : le rendre en clair
 * permettrait à quiconque lit l'export de pousser des notifications au nom du
 * client. On rend la plateforme, la date et les 8 derniers caractères — assez
 * pour que le client reconnaisse l'appareil, pas assez pour s'en servir.
 *
 * ## Pourquoi un JSON et pas un CSV
 *
 * Le contenu est imbriqué (profil + listes). Un CSV forcerait soit un
 * aplatissement qui perd la structure, soit plusieurs fichiers. Le JSON est
 * structuré, lisible par machine, et c'est le format que l'art. 20 vise.
 */
class PublicDataExportController extends Controller
{
    /**
     * GET /api/v1/user/data-export
     *
     * Réponse synchrone : le volume est borné (un client a au plus quelques
     * centaines de lignes). Une file d'attente asynchrone serait justifiée
     * au-delà, mais introduirait un état « export en cours » que rien ne
     * demande aujourd'hui.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $client */
        $client = $request->user('user_api');

        return new JsonResponse([
            'data' => [
                'exported_at' => now()->toIso8601String(),
                'format_version' => '1.0',
                'account' => $this->account($client),
                'public_profile' => $this->publicProfile($client),
                'favorites' => $this->favorites($client),
                'search_history' => $this->searchHistory($client),
                'activity_references' => $this->activityReferences($client),
                'device_tokens' => $this->deviceTokens($client),
                'not_included' => [
                    'orders_and_reservations' => __('platform.export_not_included_orders'),
                    'messages' => __('platform.export_not_included_messages'),
                ],
            ],
        ]);
    }

    /**
     * Identité de contact du client.
     *
     * On n'inclut **pas** le hash du mot de passe, ni les jetons d'accès : ce
     * sont des secrets d'authentification, pas des données personnelles
     * portables. Les rendre dans un export serait une fuite.
     *
     * @return array<string, mixed>
     */
    private function account(User $client): array
    {
        return [
            'id' => $client->id,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => $client->email,
            'phone' => $client->phone,
            'phone_e164' => $client->phone_e164,
            'phone_verified_at' => $client->phone_verified_at?->toIso8601String(),
            'preferred_language' => $client->preferred_language,
            'status' => $client->status,
            'created_at' => $client->created_at?->toIso8601String(),
            'deletion_requested_at' => $client->deletion_requested_at?->toIso8601String(),
            'purge_scheduled_at' => $client->purge_scheduled_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function publicProfile(User $client): ?array
    {
        $profile = PublicProfile::query()->find($client->id);

        if ($profile === null) {
            return null;
        }

        return [
            'preferred_locale' => $profile->preferred_locale,
            'approx_city' => $profile->approx_city,
            'approx_country' => $profile->approx_country,
            'notifications_opt_in' => $profile->notifications_opt_in,
            'notifications_consent_at' => $profile->notifications_consent_at?->toIso8601String(),
            'privacy_policy_version' => $profile->privacy_policy_version,
            'last_active_at' => $profile->last_active_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function favorites(User $client): array
    {
        return PublicFavorite::query()
            ->where('user_id', $client->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (PublicFavorite $favorite): array => [
                'target_type' => $favorite->target_type,
                'target_slug' => $favorite->target_slug,
                'created_at' => $favorite->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchHistory(User $client): array
    {
        return PublicSearchHistory::query()
            ->where('user_id', $client->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (PublicSearchHistory $entry): array => [
                'query' => $entry->query,
                'city' => $entry->city,
                'result_count' => $entry->result_count,
                'searched_at' => $entry->searched_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Références d'activité : identifiants et statuts seulement.
     *
     * C'est exactement ce que l'index contient — aucun contenu métier n'y est
     * stocké (règle d'architecture n°1), donc l'export ne peut pas en divulguer.
     *
     * @return array<int, array<string, mixed>>
     */
    private function activityReferences(User $client): array
    {
        return PublicActivityIndex::query()
            ->where('user_id', $client->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (PublicActivityIndex $row): array => [
                'vertical' => $row->vertical,
                'activity_type' => $row->activity_type,
                'reference' => $row->external_ref,
                'status' => $row->status,
                'occurred_at' => $row->occurred_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Jetons d'appareil, **tronqués**.
     *
     * Le jeton complet est un secret de transport : le rendre en clair
     * permettrait de pousser des notifications au nom du client. On rend la
     * plateforme, la date et les 8 derniers caractères — assez pour
     * reconnaître l'appareil, pas assez pour s'en servir.
     *
     * @return array<int, array<string, mixed>>
     */
    private function deviceTokens(User $client): array
    {
        return DB::table('public_device_tokens')
            ->where('user_id', $client->id)
            ->orderBy('id')
            ->get()
            ->map(static function (object $row): array {
                $token = (string) ($row->token ?? '');

                return [
                    'platform' => $row->platform ?? null,
                    'device_name' => $row->device_name ?? null,
                    'token_suffix' => $token === '' ? null : substr($token, -8),
                    'last_seen_at' => $row->last_seen_at ?? null,
                    'created_at' => $row->created_at ?? null,
                ];
            })
            ->all();
    }
}
