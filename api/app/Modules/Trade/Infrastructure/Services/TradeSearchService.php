<?php

declare(strict_types=1);

namespace App\Modules\Trade\Infrastructure\Services;

use App\Modules\Trade\Domain\Models\TradeCounterparty;
use App\Modules\Trade\Domain\Models\TradeDeal;
use App\Modules\Trade\Domain\Models\TradeProduct;
use Illuminate\Support\Facades\DB;

/**
 * Recherche transverse import-export — BC-36 TRADE, #9781.
 *
 * ## Le besoin
 *
 * « Pouvoir les retrouver facilement » : le responsable import-export doit
 * retrouver en UNE recherche un fournisseur, un produit du catalogue ou un
 * dossier de transaction. Avant ce service, chaque référentiel avait sa liste
 * séparée et il fallait savoir d'avance dans quel écran chercher.
 *
 * ## Ce que ce service ne fait PAS
 *
 * **Aucune recherche floue, aucun score inventé.** Chaque groupe interroge des
 * colonnes NOMMÉES avec un `ILIKE` échappé. Il n'y a pas de « pertinence »
 * calculée : les résultats sont triés par identifiant décroissant (les plus
 * récents d'abord), ce qui est reproductible et explicable à l'utilisateur.
 *
 * Un moteur de recherche plein texte (tsvector, trigrammes) serait la bonne
 * réponse à volume ; il n'est pas justifié ici et introduirait une dépendance
 * PostgreSQL que les tests SQLite ne couvriraient pas.
 *
 * ## Isolation tenant
 *
 * Les trois requêtes passent par des modèles qui portent `BelongsToCompany` :
 * le périmètre est appliqué par le scope global, jamais par un `where` écrit
 * à la main dans ce service (une omission serait invisible).
 */
final class TradeSearchService
{
    /** Longueur minimale de la requête : en dessous, on ne cherche pas. */
    public const MIN_QUERY_LENGTH = 2;

    /** Plafond par groupe — un écran de recherche n'est pas un export. */
    public const MAX_LIMIT = 50;

    /**
     * Recherche groupée.
     *
     * @return array{
     *     query: string,
     *     groups: array{
     *         counterparties: list<array<string, mixed>>,
     *         products: list<array<string, mixed>>,
     *         deals: list<array<string, mixed>>
     *     },
     *     total: int
     * }
     */
    public function search(string $query, int $limit = 10): array
    {
        $term = $this->likeTerm($query);
        $operator = $this->likeOperator();
        $limit = min(max($limit, 1), self::MAX_LIMIT);

        $counterparties = TradeCounterparty::query()
            ->where(static function ($builder) use ($operator, $term): void {
                $builder->where('name', $operator, $term)
                    ->orWhere('email', $operator, $term)
                    ->orWhere('city', $operator, $term);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static fn (TradeCounterparty $counterparty): array => [
                'id' => $counterparty->id,
                'name' => $counterparty->name,
                'roles' => $counterparty->roles ?? [],
                'country' => $counterparty->country,
                'city' => $counterparty->city,
                'verification_status' => $counterparty->verification_status?->value,
            ])
            ->all();

        $products = TradeProduct::query()
            ->where(static function ($builder) use ($operator, $term): void {
                $builder->where('name', $operator, $term)
                    ->orWhere('category', $operator, $term);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static fn (TradeProduct $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category,
                'origin_country' => $product->origin_country,
                'status' => $product->status,
            ])
            ->all();

        $deals = TradeDeal::query()
            ->where(static function ($builder) use ($operator, $term): void {
                $builder->where('title', $operator, $term)
                    ->orWhere('reference', $operator, $term);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static fn (TradeDeal $deal): array => [
                'id' => $deal->id,
                'reference' => $deal->reference,
                'title' => $deal->title,
                'direction' => $deal->direction?->value,
                'status' => $deal->status?->value,
            ])
            ->all();

        return [
            'query' => $query,
            'groups' => [
                'counterparties' => $counterparties,
                'products' => $products,
                'deals' => $deals,
            ],
            'total' => count($counterparties) + count($products) + count($deals),
        ];
    }

    /**
     * Motif `LIKE` échappé.
     *
     * Un `%` ou `_` saisi par l'utilisateur est un CARACTÈRE, pas un joker :
     * sans échappement, chercher « 100% » ramènerait tout le répertoire.
     */
    private function likeTerm(string $query): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);

        return '%'.$escaped.'%';
    }

    /**
     * `ilike` n'existe qu'en PostgreSQL ; ailleurs on retombe sur `like`
     * (SQLite est insensible à la casse sur ASCII par défaut).
     */
    private function likeOperator(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
