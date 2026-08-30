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
