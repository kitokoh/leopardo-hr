<?php

declare(strict_types=1);

namespace App\Modules\Retail\Application\Services;

use App\Modules\Retail\Domain\Enums\RetailOrderStatus;
use App\Modules\Retail\Domain\Enums\RetailPaymentMethod;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderItem;
use App\Modules\Retail\Domain\Models\RetailOrderPayment;
use App\Modules\Retail\Domain\Models\RetailPosRefund;
use App\Modules\Retail\Domain\Models\RetailPosSession;
use App\Modules\Retail\Domain\Models\RetailProduct;
use App\Modules\Retail\Domain\Models\RetailStockLevel;
use Illuminate\Support\Carbon;

/**
 * BC-17 RETAIL (#9209) — rapports de vente du POS Retail.
 *
 * Avant ce service, la verticale Retail n'avait AUCUN rapport (le restaurant
 * en a cinq) : un commercant ne pouvait pas repondre a « combien j'ai vendu
 * aujourd'hui ? », « quel est mon produit le plus vendu ? », « quel est
 * l'ecart de caisse de la semaine ? ».
 *
 * Toutes les agregations sont faites SERVEUR (jamais de somme cote client),
 * en minor units, et bornees par `company_id` — l'isolation tenant est la
 * garde premiere de chaque requete. Les bornes de dates sont inclusives sur
 * la journee entiere (`from` 00:00:00, `to` 23:59:59).
 *
 * Pas de facade Laravel (purete de couche Application, garde #6568) : les
 * requetes passent par les modeles Eloquent du module, jamais par la facade
 * `DB`.
 */
final class RetailReportService
{
    /**
     * Rapport de ventes : CA, nombre de tickets, panier moyen, repartition
     * par moyen de paiement, remboursements.
     *
     * @return array<string, mixed>
     */
    public function salesReport(
        string $companyId,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?int $locationId = null,
    ): array {
        $orders = $this->completedOrdersQuery($companyId, $from, $to, $locationId)->get();

        $grossMinor = (int) $orders->sum('total_minor');
        $ticketCount = $orders->count();
        $averageBasketMinor = $ticketCount > 0 ? intdiv($grossMinor, $ticketCount) : 0;

        $payments = RetailOrderPayment::query()
            ->where('company_id', $companyId)
            ->where('status', 'captured')
            ->whereIn('order_id', $orders->pluck('id')->all())
            ->get();

        $byMethod = [];
        foreach (RetailPaymentMethod::cases() as $method) {
            $byMethod[$method->value] = (int) $payments
                ->where('method', $method)
                ->sum('amount_minor');
        }

        $refundsMinor = (int) RetailPosRefund::query()
            ->where('company_id', $companyId)
            ->whereIn('order_id', $orders->pluck('id')->all())
            ->sum('amount_minor');

        return [
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'location_id' => $locationId,
            'gross_sales_minor' => $grossMinor,
            'refunds_minor' => $refundsMinor,
            'net_sales_minor' => $grossMinor - $refundsMinor,
            'ticket_count' => $ticketCount,
            'average_basket_minor' => $averageBasketMinor,
            'currency' => $orders->first()->currency ?? 'XOF',
            'by_payment_method' => $byMethod,
        ];
    }

    /**
     * Top produits : quantites vendues et CA, decroissant.
     *
     * @return list<array<string, mixed>>
     */
    public function topProductsReport(
        string $companyId,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?int $locationId = null,
        int $limit = 20,
    ): array {
        $orderIds = $this->completedOrdersQuery($companyId, $from, $to, $locationId)
            ->pluck('id')
            ->all();

        if ($orderIds === []) {
            return [];
        }

        $rows = RetailOrderItem::query()
            ->where('company_id', $companyId)
            ->whereIn('order_id', $orderIds)
            ->selectRaw('product_id, product_name, SUM(quantity) as quantity_sold, SUM(line_total_minor) as revenue_minor')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('revenue_minor')
            ->limit(max(1, min(100, $limit)))
            ->get();

        // Les alias d'agregation ne sont pas des attributs declares du modele :
        // on les lit via getAttribute() pour rester lisible par PHPStan.
        // array_values() est explicite : la collection indexe par la cle du
        // modele, or le contrat de sortie est une LISTE (PHPStan niveau 8).
        return array_values($rows->map(static fn (RetailOrderItem $row): array => [
            'product_id' => (int) $row->getAttribute('product_id'),
            'product_name' => (string) $row->getAttribute('product_name'),
            'quantity_sold' => (float) $row->getAttribute('quantity_sold'),
            'revenue_minor' => (int) $row->getAttribute('revenue_minor'),
        ])->all());
    }

    /**
     * Clotures de caisse : attendu, compte, ecart.
     *
     * @return list<array<string, mixed>>
     */
    public function sessionsReport(
        string $companyId,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?int $locationId = null,
    ): array {
        $query = RetailPosSession::query()
            ->where('company_id', $companyId)
            ->where('status', 'closed');

        if ($from !== null) {
            $query->where('closed_at', '>=', $from->copy()->startOfDay());
        }

        if ($to !== null) {
            $query->where('closed_at', '<=', $to->copy()->endOfDay());
        }

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        return array_values($query->orderByDesc('closed_at')->get()->map(static fn (RetailPosSession $session): array => [
            'id' => $session->id,
            'location_id' => $session->location_id,
            'opened_at' => $session->opened_at?->toIso8601String(),
            'closed_at' => $session->closed_at?->toIso8601String(),
            'opening_cash_minor' => $session->opening_cash_minor,
            'expected_cash_minor' => $session->expected_cash_minor,
            'counted_cash_minor' => $session->counted_cash_minor,
            'variance_minor' => $session->variance_minor,
            'variance_reason' => $session->variance_reason,
        ])->all());
    }

    /**
     * Valorisation du stock : quantite, valeur au cout, valeur au prix de vente.
     *
     * @return array<string, mixed>
     */
    public function stockValuationReport(string $companyId, ?int $locationId = null): array
    {
        $query = RetailStockLevel::query()->where('company_id', $companyId);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        $levels = $query->get();
        $products = RetailProduct::query()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('id');

        $costValueMinor = 0;
        $retailValueMinor = 0;
        $lines = [];

        foreach ($levels as $level) {
            $product = $products->get((int) $level->product_id);

            if ($product === null) {
                continue;
            }

            $quantity = (float) $level->quantity;
            $costMinor = (int) round($quantity * (int) $product->cost_minor);
            $retailMinor = (int) round($quantity * (int) $product->price_minor);

            $costValueMinor += $costMinor;
            $retailValueMinor += $retailMinor;

            $lines[] = [
                'product_id' => (int) $product->id,
                'product_name' => (string) $product->name,
                'location_id' => (int) $level->location_id,
                'quantity' => $quantity,
                'cost_value_minor' => $costMinor,
                'retail_value_minor' => $retailMinor,
            ];
        }

        return [
            'location_id' => $locationId,
            'cost_value_minor' => $costValueMinor,
            'retail_value_minor' => $retailValueMinor,
            'margin_minor' => $retailValueMinor - $costValueMinor,
            'lines' => $lines,
        ];
    }

    /**
     * Convertit un rapport en CSV (export `?format=csv`).
     *
     * Les rapports a lignes plates (top produits, clotures, valorisation)
     * produisent un tableau ; les rapports agreges (ventes) produisent une
     * ligne cle/valeur. Fonction PURE : testable sans base.
     *
     * @param  array<string, mixed>  $report
     */
    public function toCsv(array $report): string
    {
        $lines = $report['lines'] ?? null;

        if (is_array($lines) && $lines !== []) {
            // `$report` est un array<string, mixed> : on ne fait confiance a
            // aucune forme implicite, chaque ligne doit etre un tableau.
            $rows = [];
            foreach ($lines as $line) {
                if (is_array($line)) {
                    /** @var array<string, mixed> $line */
                    $rows[] = $line;
                }
            }

            if ($rows !== []) {
                return $this->rowsToCsv($rows);
            }
        }

        // Rapport agrege : une ligne par cle scalaire.
        $rows = [];
        foreach ($report as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $rows[] = ['key' => (string) $key, 'value' => $value === null ? '' : (string) $value];
            }
        }

        return $this->rowsToCsv($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function rowsToCsv(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, array_keys($rows[0]));

        foreach ($rows as $row) {
            fputcsv($handle, array_map(
                static fn ($value): string => is_scalar($value) || $value === null
                    ? (string) $value
                    : (json_encode($value) ?: ''),
                $row,
            ));
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Commandes ENCAISSEES du perimetre (statut `completed`), bornees par
     * tenant, dates et emplacement.
     *
     * @return \Illuminate\Database\Eloquent\Builder<RetailOrder>
     */
    private function completedOrdersQuery(
        string $companyId,
        ?Carbon $from,
        ?Carbon $to,
        ?int $locationId,
    ): \Illuminate\Database\Eloquent\Builder {
        $query = RetailOrder::query()
            ->where('company_id', $companyId)
            ->where('status', RetailOrderStatus::Completed->value);

        if ($from !== null) {
            $query->where('created_at', '>=', $from->copy()->startOfDay());
        }

        if ($to !== null) {
            $query->where('created_at', '<=', $to->copy()->endOfDay());
        }

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        return $query;
    }
}
