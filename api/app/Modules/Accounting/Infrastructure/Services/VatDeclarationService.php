<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Déclaration TVA simplifiée par période — issue #5271.
 *
 * Agrège les documents comptables du tenant sur une période mensuelle :
 *   - TVA collectée  : factures (invoice) + reçus (receipt) ;
 *   - TVA déductible : avoirs (credit_note) ;
 *   - Net à déclarer : collectée − déductible.
 *
 * Règles documentées :
 *   - les documents `draft` et `cancelled` sont exclus (une déclaration porte
 *     sur l'émis définitif, pas sur l'annulé) ;
 *   - l'assiette est le montant HT (subtotal_ht), la taxe est le montant de
 *     TVA (tax_amount), le total est TTC (total_ttc) ;
 *   - détail par taux de TVA (tva_rate du document) + totaux arrondis à 2
 *     décimales (arrondi comptable standard).
 *
 * Isolation tenant : l'agrégation filtre explicitement par company_id (la
 * ressource n'expose jamais d'id d'URL — fail-closed #3727).
 */
final class VatDeclarationService
{
    /** Types de documents générant de la TVA collectée. */
    private const COLLECTED_TYPES = [DocumentType::Invoice, DocumentType::Receipt];

    /** Types de documents générant de la TVA déductible. */
    private const DEDUCTIBLE_TYPES = [DocumentType::CreditNote];

    /**
     * Déclaration TVA d'une période mensuelle (format YYYY-MM).
     *
     * @return array<string, mixed>
     */
    public function declaration(Company $company, string $period): array
    {
        [$from, $to] = $this->periodBounds($period);

        $collected = $this->aggregate($company->id, self::COLLECTED_TYPES, $from, $to);
        $deductible = $this->aggregate($company->id, self::DEDUCTIBLE_TYPES, $from, $to);

        return [
            'period' => [
                'label' => $period,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'currency' => $this->currency($company),
            'collected' => $collected,
            'deductible' => $deductible,
            'net' => [
                'base' => round($collected['base'] - $deductible['base'], 2),
                'tax' => round($collected['tax'] - $deductible['tax'], 2),
                'total' => round($collected['total'] - $deductible['total'], 2),
            ],
        ];
    }

    /**
     * Export CSV de la déclaration (une ligne par taux + ligne net).
     *
     * @param  array<string, mixed>  $declaration
     */
    public function toCsv(array $declaration): Response
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            abort(500, 'CSV_EXPORT_FAILED');
        }

        fputcsv($handle, ['periode', $declaration['period']['label'], 'devise', $declaration['currency']]);
        fputcsv($handle, []);
        fputcsv($handle, ['type', 'taux', 'assiette_ht', 'taxe', 'total_ttc']);

        foreach ($declaration['collected']['by_rate'] as $row) {
            fputcsv($handle, ['collectee', $row['rate'], $row['base'], $row['tax'], $row['total']]);
        }

        foreach ($declaration['deductible']['by_rate'] as $row) {
            fputcsv($handle, ['deductible', $row['rate'], $row['base'], $row['tax'], $row['total']]);
        }

        $net = $declaration['net'];
        fputcsv($handle, []);
        fputcsv($handle, ['net', '', $net['base'], $net['tax'], $net['total']]);

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $period = $declaration['period'];

        return response($csv ?: '', 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="vat-declaration-'.$period['label'].'.csv"',
        ]);
    }

    /**
     * Agrégation par taux de TVA pour une liste de types de documents.
     *
     * @param  array<int, DocumentType>  $types
     * @return array{base: float, tax: float, total: float, by_rate: array<int, array{rate: float, base: float, tax: float, total: float}>}
     */
    private function aggregate(string $companyId, array $types, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('accounting_documents')
            ->selectRaw('COALESCE(tva_rate, 0) as rate')
            ->selectRaw('SUM(subtotal_ht) as base')
            ->selectRaw('SUM(tax_amount) as tax')
            ->selectRaw('SUM(total_ttc) as total')
            ->where('company_id', $companyId)
            ->whereIn('type', array_map(fn (DocumentType $type): string => $type->value, $types))
            ->whereNotIn('status', [DocumentStatus::Draft->value, DocumentStatus::Cancelled->value])
            ->whereDate('issue_date', '>=', $from->toDateString())
            ->whereDate('issue_date', '<=', $to->toDateString())
            ->groupBy('rate')
            ->orderBy('rate')
            ->get();

        $byRate = [];

        foreach ($rows as $row) {
            $byRate[] = [
                'rate' => round((float) $row->rate, 2),
                'base' => round((float) $row->base, 2),
                'tax' => round((float) $row->tax, 2),
                'total' => round((float) $row->total, 2),
            ];
        }

        $sum = static fn (string $column): float => round((float) array_sum($rows->pluck($column)->all()), 2);

        return [
            'base' => $sum('base'),
            'tax' => $sum('tax'),
            'total' => $sum('total'),
            'by_rate' => $byRate,
        ];
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function periodBounds(string $period): array
    {
        if (preg_match('/^\d{4}-\d{2}$/', $period) !== 1) {
            throw new InvalidArgumentException('accounting.vat_period_invalid');
        }

        $from = CarbonImmutable::createFromFormat('!Y-m', $period);

        // createFromFormat renvoie false en cas d'échec ; instanceof couvre
        // null et false (vérification PHPStan-compatible sur CarbonImmutable).
        if (! $from instanceof CarbonImmutable) {
            throw new InvalidArgumentException('accounting.vat_period_invalid');
        }

        return [$from, $from->endOfMonth()];
    }

    private function currency(Company $company): string
    {
        $settings = AccountingSettings::query()
            ->where('company_id', $company->id)
            ->first();

        return strtoupper($settings->currency ?? (string) ($company->currency ?? 'DZD'));
    }
}
