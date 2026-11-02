<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Services;

use App\Core\Auth\Domain\Models\User;
use App\Modules\Platform\Domain\Models\PublicActivityIndex;
use App\Modules\Platform\Domain\Models\PublicFavorite;
use App\Modules\Platform\Domain\Models\PublicSearchHistory;
use App\Shared\Discovery\PublicActivityLocator;
use App\Shared\Discovery\PublicProfileLocator;
use Illuminate\Support\Collection;

/**
 * COULOIR 16 — M3 (#9105) · Activités, favoris et historique de recherche du
 * client public.
 *
 * ## Ce que ce service n'est PAS
 *
 * Ce n'est pas un nouveau Bounded Context. C'est la couche applicative du
 * CANAL public : elle lit des index de références et délègue toute lecture
 * métier aux verticales. Aucune règle métier n'est écrite ici — un statut de
 * commande n'est jamais calculé, seulement relu.
 *
 * ## Les trois invariants
 *
 *  1. **L'index n'autorise rien.** `activitiesFor()` lit
 *     `public_activity_index`, puis rejoue pour CHAQUE ligne le contrôle
 *     d'accès dans le contexte tenant via `PublicActivityLocator`. Une ligne
 *     dont le provider ne confirme pas l'appartenance est silencieusement
 *     écartée — jamais affichée, jamais signalée comme une erreur (ce serait
 *     un oracle).
 *  2. **Un module sans contrat n'apparaît pas.** `activitySections()` ne
 *     renvoie que les verticales dont le provider déclare le type. L'onglet
 *     s'appuie dessus : il n'affiche jamais une section vide par construction.
 *  3. **Le client ne fournit jamais de tenant.** Favoris et historique sont
 *     indexés par `user_id` seul ; le tenant d'un favori est résolu à
 *     l'affichage, fail-closed.
 */
final class PublicActivityService
{
    /** Nombre maximal d'activites renvoyees (borne de charge utile). */
    public const MAX_ACTIVITIES = 50;

    /** Nombre maximal de favoris renvoyes. */
    public const MAX_FAVORITES = 100;

    /** Nombre maximal d'entrees d'historique renvoyees. */
    public const MAX_HISTORY = 20;

    public function __construct(
        private readonly PublicActivityLocator $activities,
        private readonly PublicProfileLocator $profiles,
    ) {}

    /**
     * Sections de l'onglet Activités, limitées aux modules qui ont un contrat.
     *
     * @return list<array{vertical: string, types: list<string>}>
     */
    public function activitySections(): array
    {
        $sections = [];

        foreach ($this->activities->verticals() as $vertical) {
            $types = [];

            foreach (PublicActivityIndex::TYPES as $type) {
                if ($this->activities->supports($vertical, $type)) {
                    $types[] = $type;
                }
            }

            if ($types !== []) {
                $sections[] = ['vertical' => $vertical, 'types' => $types];
            }
        }

        return $sections;
    }

    /**
     * Activités d'un client, du plus récent au plus ancien.
     *
     * Chaque ligne de l'index est REVALIDÉE dans son contexte tenant. Une ligne
     * que le provider ne confirme pas est écartée sans bruit : l'index peut
     * contenir une référence supprimée côté tenant, ou une ligne dont
     * l'appartenance a changé.
     *
     * @return Collection<int, array{vertical: string, activity_type: string, reference: string, status: string, title: string, occurred_at: string}>
     */
    public function activitiesFor(User $client, int $limit = self::MAX_ACTIVITIES): Collection
    {
        $limit = max(1, min(self::MAX_ACTIVITIES, $limit));

        $rows = PublicActivityIndex::query()
            ->where('user_id', $client->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $verified = new Collection;

        foreach ($rows as $row) {
            // Un module sans contrat n'apparaît pas, même si une ligne
            // d'index existe (donnée historique, verticale désactivée).
            if (! $this->activities->supports($row->vertical, $row->activity_type)) {
                continue;
            }

            $activity = $this->activities->find(
                $row->vertical,
                $row->activity_type,
                $row->external_ref,
                (int) $client->id,
            );

            if ($activity === null) {
                continue;
            }

            $verified->push([
                'vertical' => $row->vertical,
                'activity_type' => $row->activity_type,
                'reference' => $activity['reference'],
                // Statut RELU côté tenant, jamais recopié de l'index.
                'status' => $activity['status'],
                'title' => $activity['title'],
                'occurred_at' => $activity['occurred_at'],
            ]);
        }

        return $verified;
    }

    /**
     * Favoris d'un client, du plus récent au plus ancien.
     *
     * Le tenant de chaque favori est résolu à l'affichage : un profil dépublié
     * n'est pas supprimé du favori (la personne l'a choisi), mais il est marqué
     * `available: false` pour que l'écran propose autre chose au lieu d'un
     * bouton mort.
     *
     * @return Collection<int, array{target_type: string, target_slug: string, display_name: string|null, available: bool, created_at: string|null}>
     */
    public function favoritesFor(User $client, int $limit = self::MAX_FAVORITES): Collection
    {
        $limit = max(1, min(self::MAX_FAVORITES, $limit));

        return PublicFavorite::query()
            ->where('user_id', $client->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (PublicFavorite $favorite): array {
                $target = $this->profiles->locate($favorite->target_type, $favorite->target_slug);

                return [
                    'target_type' => $favorite->target_type,
                    'target_slug' => $favorite->target_slug,
                    // Nom commercial seulement : aucun identifiant de tenant.
                    'display_name' => $target['display_name'] ?? null,
                    'available' => $target !== null,
                    'created_at' => $favorite->created_at?->toIso8601String(),
                ];
            })
            ->values();
    }

    /**
     * Ajoute un favori. Idempotent : rejouer l'ajout ne crée pas de doublon.
     *
     * Le profil est résolu AVANT l'écriture : on ne met pas en favori un slug
     * qui n'existe pas. Cela évite qu'un client remplisse sa liste de favoris
     * morts en tapant des slugs au hasard, et cela empêche d'utiliser les
     * favoris comme oracle d'existence — un slug inconnu est refusé de la même
     * façon qu'un slug dépublié.
     *
     * @return array{created: bool, favorite: array<string, mixed>}
     */
    public function addFavorite(User $client, string $targetType, string $targetSlug): array
    {
        $target = $this->profiles->locate($targetType, $targetSlug);

        if ($target === null) {
            throw new \InvalidArgumentException('PROFILE_NOT_AVAILABLE');
        }

        $favorite = PublicFavorite::query()->firstOrCreate(
            [
                'user_id' => $client->id,
                'target_type' => $targetType,
                'target_slug' => $targetSlug,
            ],
        );

        return [
            'created' => $favorite->wasRecentlyCreated,
            'favorite' => [
                'target_type' => $favorite->target_type,
                'target_slug' => $favorite->target_slug,
                'display_name' => $target['display_name'],
                'available' => true,
                'created_at' => $favorite->created_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Retire un favori. Idempotent : retirer un favori absent n'est pas une
     * erreur (l'intention de la personne est satisfaite).
     */
    public function removeFavorite(User $client, string $targetType, string $targetSlug): bool
    {
        $deleted = PublicFavorite::query()
            ->where('user_id', $client->id)
            ->where('target_type', $targetType)
            ->where('target_slug', $targetSlug)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Dernières recherches d'un client.
     *
     * @return Collection<int, array{query: string, city: string|null, result_count: int, searched_at: string|null}>
     */
    public function searchHistoryFor(User $client, int $limit = self::MAX_HISTORY): Collection
    {
        $limit = max(1, min(self::MAX_HISTORY, $limit));

        return PublicSearchHistory::query()
            ->where('user_id', $client->id)
            ->orderByDesc('searched_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static fn (PublicSearchHistory $entry): array => [
                'query' => $entry->query,
                'city' => $entry->city,
                'result_count' => $entry->result_count,
                'searched_at' => $entry->searched_at?->toIso8601String(),
            ])
            ->values();
    }

    /**
     * Enregistre une recherche. Idempotent par `(user, query, city)` : répéter
     * la même recherche la REMONTE au lieu d'empiler des doublons.
     *
     * Aucune coordonnée n'est acceptée : `city` est une ville, pas une position.
     */
    public function recordSearch(User $client, string $query, ?string $city, int $resultCount): void
    {
        $trimmed = trim($query);

        if ($trimmed === '') {
            return;
        }

        PublicSearchHistory::query()->updateOrCreate(
            [
                'user_id' => $client->id,
                'query' => mb_substr($trimmed, 0, 160),
                'city' => $city !== null && trim($city) !== '' ? mb_substr(trim($city), 0, 120) : null,
            ],
            [
                'result_count' => max(0, $resultCount),
                'searched_at' => now(),
            ],
        );
    }

    /**
     * Efface l'historique d'un client (droit à l'effacement, écran Profil).
     */
    public function clearSearchHistory(User $client): int
    {
        return PublicSearchHistory::query()->where('user_id', $client->id)->delete();
    }
}
