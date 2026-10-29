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

        // #8396 — le partage répondait 201. `POST /share` n'altère pas
        // l'identité de la ressource : il trace un évènement d'engagement et
        // renvoie l'agrégat, comme `like`/`rate` qui répondent 200. Un 201
        // fait en outre échouer tout client qui n'accepte que les statuts de
        // succès « lecture » sur cette surface.
        return new JsonResponse(['data' => ['shared' => true, 'share_id' => $share->id]]);
    }

    public function rate(Request $request, TravelArticle $travelArticle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelArticle->company_id) {
            abort(404);
        }

        if ($actor->cannot('engage', $travelArticle)) {
            abort(403);
        }

        // #8396 — la note se lisait uniquement dans `rating` (contrat OpenAPI).
        // Les clients éditoriaux envoient `stars` : ils recevaient un 422 alors
        // que leur note était valide. Les deux clés sont acceptées, `rating`
        // restant la canonique du contrat documenté.
        $raw = $request->json('rating') ?? $request->json('stars');
        $rating = is_numeric($raw) ? (int) $raw : 0;

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

        return new JsonResponse(['data' => $this->aggregateData($actor, $travelArticle)]);
    }

    /**
     * Agrégats d'engagement d'un article (likes, partages, note moyenne).
     */
    public function aggregates(Request $request, TravelArticle $travelArticle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelArticle->company_id) {
            abort(404);
        }

        return new JsonResponse(['data' => $this->aggregateData($actor, $travelArticle)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregateData(Employee $actor, TravelArticle $article): array
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
    }

    /**
     * Agrégats d'engagement d'un article (likes, partages, note moyenne).
     *
     * #8396 — le paramètre s'appelait `$article` alors que la route expose
     * `{travelArticle}`. La liaison implicite de Laravel se fait par NOM de
     * paramètre : `$article` n'était jamais résolu et la route répondait 404
     * pour tout identifiant valide. Aligné sur le nom de la route.
     */
    public function summary(Request $request, TravelArticle $travelArticle): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($travelArticle->company_id !== $actor->company_id) {
            abort(404);
        }

        return response()->json(['data' => [
            'likes_count' => $this->likesCount($travelArticle),
            'shares_count' => TravelShare::query()
                ->where('company_id', $travelArticle->company_id)
                ->where('article_id', $travelArticle->id)
                ->count(),
            'rating_avg' => $this->ratingAverage($travelArticle),
            'ratings_count' => $this->ratingsCount($travelArticle),
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
        // #8396 — `avg('stars')` visait une colonne inexistante : la table
        // `travel_ratings` stocke `rating` (voir migration 6106 et le modèle).
        // PostgreSQL laissait passer la requête et renvoyait `null` : la note
        // moyenne n'a JAMAIS été exposée, même avec des notes en base — le
        // client affichait donc « 0 avis » sur des articles notés.
        $avg = TravelRating::query()
            ->where('company_id', $article->company_id)
            ->where('article_id', $article->id)
            ->avg('rating');

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
