<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelArticle;
use App\Modules\TravelAgency\Domain\Models\TravelLike;
use App\Modules\TravelAgency\Domain\Models\TravelRating;
use App\Modules\TravelAgency\Domain\Models\TravelShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-903 (#6106) — Likes / partages / notes (engagement éditorial).
 * Unicité (tenant, article, acteur) anti-doublon ; agrégats serveur ;
 * notes bornées 1..5 (anti-spam : une note par acteur).
 */
class TravelEngagementController extends Controller
{
    public function like(Request $request, TravelArticle $travelArticle): JsonResponse
 * TRAVEL-903 (#6106) — Likes / partages / notes.
 *
 * Unicité (tenant, article, acteur) : un acteur = un like par cible
 * (critère d'acceptation), une seule note par cible. Agrégats dérivés
 * (COUNT/AVG serveur, jamais stockés) — anti-spam.
 */
class TravelEngagementController extends Controller
{
    public function like(Request $request, TravelArticle $article): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelArticle->company_id) {
            abort(404);
        }

        if ($actor->cannot('engage', $travelArticle)) {
            abort(403);
        }

        $like = TravelLike::query()->firstOrCreate(
            [
                'company_id' => $actor->company_id,
                'article_id' => $travelArticle->id,
                'actor_type' => 'employee',
                'actor_id' => $actor->id,
            ],
        );

        return new JsonResponse(['data' => ['liked' => true, 'like_id' => $like->id]]);
    }

    public function unlike(Request $request, TravelArticle $travelArticle): JsonResponse
        if ($article->company_id !== $actor->company_id) {
            abort(404);
        }

        // Idempotent : un like existant est renvoyé sans doublon.
        $like = TravelLike::query()->firstOrCreate(
            [
                'company_id' => $actor->company_id,
                'article_id' => $article->id,
                'actor_user_id' => $actor->id,
                'actor_identifier' => null,
            ],
            ['actor_type' => 'employee'],
        );

        return response()->json(['data' => ['liked' => true, 'likes_count' => $this->likesCount($article)]]);
    }

    public function unlike(Request $request, TravelArticle $article): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelArticle->company_id) {
            abort(404);
        }

        if ($actor->cannot('engage', $travelArticle)) {
            abort(403);
        }

        TravelLike::query()
            ->where('company_id', $actor->company_id)
            ->where('article_id', $travelArticle->id)
            ->where('actor_type', 'employee')
            ->where('actor_id', $actor->id)
            ->delete();

        return new JsonResponse(['data' => ['liked' => false]]);
    }

    public function share(Request $request, TravelArticle $travelArticle): JsonResponse
        if ($article->company_id !== $actor->company_id) {
            abort(404);
        }

        TravelLike::query()
            ->where('company_id', $actor->company_id)
            ->where('article_id', $article->id)
            ->where('actor_user_id', $actor->id)
            ->delete();

        return response()->json(['data' => ['liked' => false, 'likes_count' => $this->likesCount($article)]]);
    }

    public function share(Request $request, TravelArticle $article): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelArticle->company_id) {
            abort(404);
        }

        if ($actor->cannot('engage', $travelArticle)) {
            abort(403);
        }

        $channel = (string) $request->json('channel', 'link');

        if (! in_array($channel, ['link', 'whatsapp', 'facebook', 'x', 'email'], true)) {
            abort(422, 'Unknown share channel.');
        }

        $share = TravelShare::query()->create([
            'company_id' => $actor->company_id,
            'article_id' => $travelArticle->id,
            'channel' => $channel,
            'actor_type' => 'employee',
            'actor_id' => $actor->id,
        ]);

        return new JsonResponse(['data' => ['shared' => true, 'share_id' => $share->id]], 201);
    }

    public function rate(Request $request, TravelArticle $travelArticle): JsonResponse
        if ($article->company_id !== $actor->company_id) {
            abort(404);
        }

        $data = $request->validate(['channel' => ['nullable', 'string', 'max:40']]);

        TravelShare::query()->create([
            'company_id' => $actor->company_id,
            'article_id' => $article->id,
            'actor_type' => 'employee',
            'actor_user_id' => $actor->id,
            'channel' => $data['channel'] ?? null,
        ]);

        return response()->json(['data' => ['shared' => true]]);
    }

    public function rate(Request $request, TravelArticle $article): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelArticle->company_id) {
            abort(404);
        }

        if ($actor->cannot('engage', $travelArticle)) {
            abort(403);
        }

        $rating = (int) $request->json('rating');

        if ($rating < 1 || $rating > 5) {
            abort(422, 'Rating must be between 1 and 5.');
        }

        TravelRating::query()->updateOrCreate(
            [
                'company_id' => $actor->company_id,
                'article_id' => $travelArticle->id,
                'actor_type' => 'employee',
                'actor_id' => $actor->id,
            ],
            ['rating' => $rating],
        );

        return new JsonResponse(['data' => $this->aggregates($actor, $travelArticle)]);
    }

    /**
     * Agrégats d'engagement d'un article (likes, partages, note moyenne).
     */
    public function aggregates(Request $request, TravelArticle $travelArticle): JsonResponse
        if ($article->company_id !== $actor->company_id) {
            abort(404);
        }

        $data = $request->validate(['stars' => ['required', 'integer', 'min:1', 'max:5']]);

        // Une seule note par acteur/cible : updateOrCreate (idempotent).
        TravelRating::query()->updateOrCreate(
            [
                'company_id' => $actor->company_id,
                'article_id' => $article->id,
                'actor_user_id' => $actor->id,
                'actor_identifier' => null,
            ],
            [
                'actor_type' => 'employee',
                'stars' => (int) $data['stars'],
            ],
        );

        return response()->json(['data' => [
            'rating_avg' => $this->ratingAverage($article),
            'ratings_count' => $this->ratingsCount($article),
        ]]);
    }

    public function summary(Request $request, TravelArticle $article): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelArticle->company_id) {
            abort(404);
        }

        return new JsonResponse(['data' => $this->aggregates($actor, $travelArticle)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregates(Employee $actor, TravelArticle $article): array
    {
        $likes = TravelLike::query()
            ->where('company_id', $actor->company_id)
            ->where('article_id', $article->id)
            ->count();

        $shares = TravelShare::query()
            ->where('company_id', $actor->company_id)
            ->where('article_id', $article->id)
            ->count();

        $ratings = TravelRating::query()
            ->where('company_id', $actor->company_id)
            ->where('article_id', $article->id)
            ->get(['rating']);

        $ratingsCount = $ratings->count();
        $average = $ratingsCount > 0
            ? round($ratings->sum('rating') / $ratingsCount, 2)
            : 0.0;

        return [
            'article_id' => $article->id,
            'likes_count' => $likes,
            'shares_count' => $shares,
            'ratings_count' => $ratingsCount,
            'average_rating' => $average,
        ];
        if ($article->company_id !== $actor->company_id) {
            abort(404);
        }

        return response()->json(['data' => [
            'likes_count' => $this->likesCount($article),
            'shares_count' => TravelShare::query()
                ->where('company_id', $article->company_id)
                ->where('article_id', $article->id)
                ->count(),
            'rating_avg' => $this->ratingAverage($article),
            'ratings_count' => $this->ratingsCount($article),
        ]]);
    }

    private function likesCount(TravelArticle $article): int
    {
        return TravelLike::query()
            ->where('company_id', $article->company_id)
            ->where('article_id', $article->id)
            ->count();
    }

    private function ratingAverage(TravelArticle $article): ?float
    {
        $avg = TravelRating::query()
            ->where('company_id', $article->company_id)
            ->where('article_id', $article->id)
            ->avg('stars');

        return $avg === null ? null : round((float) $avg, 2);
    }

    private function ratingsCount(TravelArticle $article): int
    {
        return TravelRating::query()
            ->where('company_id', $article->company_id)
            ->where('article_id', $article->id)
            ->count();
    }
}
