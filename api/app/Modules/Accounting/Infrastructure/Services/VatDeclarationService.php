<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Exceptions\DomainException;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

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
    /**
     * Types de documents générant de la TVA collectée.
     *
     * #9367 : les avoirs de vente ne sont plus comptés ici — ils ne collectent
     * pas de TVA, ils en **annulent**. Voir `CREDIT_TYPES`.
     */
    private const COLLECTED_TYPES = [DocumentType::Invoice, DocumentType::Receipt];

    /**
     * Types de documents générant de la TVA déductible.
     *
     * #9367 : la TVA déductible provient des **factures d'achat** (colonne
     * `deductible`) ; les avoirs clients ne sont pas une TVA déductible. Les
     * avoirs fournisseurs annulent de la TVA déductible (voir `PURCHASE_CREDIT_TYPES`).
     */
    private const DEDUCTIBLE_TYPES = [DocumentType::PurchaseInvoice];

    /** Avoirs de vente : annulent de la TVA collectée. */
    private const SALES_CREDIT_TYPES = [DocumentType::CreditNote];

    /** Avoirs d'achat : annulent de la TVA déductible. */
    private const PURCHASE_CREDIT_TYPES = [DocumentType::PurchaseCreditNote];

    /**
     * Déclaration TVA d'une période mensuelle (format YYYY-MM).
     *
     * @return array<string, mixed>
     */
    public function declaration(Company $company, string $period): array
    {
        [$from, $to] = $this->periodBounds($period);

        // #9367 — collected = ventes − avoirs de vente ; deductible = achats −
        // avoirs d'achat. Avant, la TVA déductible était approximée par les
        // avoirs **clients**, ce qui n'a aucun sens comptable.
        $collected = $this->subtract(
            $this->aggregate($company->id, self::COLLECTED_TYPES, $from, $to),
            $this->aggregate($company->id, self::SALES_CREDIT_TYPES, $from, $to),
        );

        $deductible = $this->subtract(
            $this->aggregate($company->id, self::DEDUCTIBLE_TYPES, $from, $to),
            $this->aggregate($company->id, self::PURCHASE_CREDIT_TYPES, $from, $to),
        );

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
            // #9373 — documents de la période exprimés dans une autre devise que
            // celle de la déclaration : ils ne sont pas additionnés (une TVA
            // déclarée en DZD ne peut pas inclure un montant en EUR). Le
            // comptable doit les convertir en amont ; on le lui signale au lieu
            // de produire une déclaration silencieusement fausse.
            'unconverted_documents' => $this->unconvertedDocuments($company, $from, $to),
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
        $reference = $this->referenceCurrency($companyId);

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
            // #9373 — n'agréger que les documents libellés dans la devise de
            // référence : additionner des assiettes en EUR et en DZD n'a aucun
            // sens fiscal. Les autres sont comptés par `unconvertedDocuments()`.
            ->where(static function ($query) use ($reference): void {
                $query->where(static function ($inner) use ($reference): void {
                    $inner->whereRaw('UPPER(currency) = ?', [$reference])->orWhereNull('currency');
                });
            })
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
     * Soustraction d'une agrégation d'avoirs d'une agrégation de factures.
     *
     * Les taux présents seulement dans les avoirs (cas d'un avoir sur un taux
     * qu'aucune facture de la période n'utilise) sont conservés en négatif :
     * les masquer fausserait le net déclaré.
     *
     * @param  array{base: float, tax: float, total: float, by_rate: array<int, array{rate: float, base: float, tax: float, total: float}>}  $invoices
     * @param  array{base: float, tax: float, total: float, by_rate: array<int, array{rate: float, base: float, tax: float, total: float}>}  $credits
     * @return array{base: float, tax: float, total: float, by_rate: array<int, array{rate: float, base: float, tax: float, total: float}>}
     */
    private function subtract(array $invoices, array $credits): array
    {
        /** @var array<string, array{rate: float, base: float, tax: float, total: float}> $byRate */
        $byRate = [];

        foreach ($invoices['by_rate'] as $row) {
            $key = number_format($row['rate'], 4, '.', '');
            $byRate[$key] = ['rate' => $row['rate'], 'base' => $row['base'], 'tax' => $row['tax'], 'total' => $row['total']];
        }

        foreach ($credits['by_rate'] as $row) {
            $key = number_format($row['rate'], 4, '.', '');
            $current = $byRate[$key] ?? ['rate' => $row['rate'], 'base' => 0.0, 'tax' => 0.0, 'total' => 0.0];
            $byRate[$key] = [
                'rate' => $row['rate'],
                'base' => round($current['base'] - $row['base'], 2),
                'tax' => round($current['tax'] - $row['tax'], 2),
                'total' => round($current['total'] - $row['total'], 2),
            ];
        }

        ksort($byRate, SORT_NUMERIC);

        return [
            'base' => round($invoices['base'] - $credits['base'], 2),
            'tax' => round($invoices['tax'] - $credits['tax'], 2),
            'total' => round($invoices['total'] - $credits['total'], 2),
            'by_rate' => array_values($byRate),
        ];
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function periodBounds(string $period): array
    {
        if (preg_match('/^\d{4}-\d{2}$/', $period) !== 1) {
            throw new DomainException('ACCOUNTING_VAT_PERIOD_INVALID', 422, 'ACCOUNTING_VAT_PERIOD_INVALID');
        }

        $from = CarbonImmutable::createFromFormat('!Y-m', $period);

        // createFromFormat renvoie false en cas d'échec ; instanceof couvre
        // null et false (vérification PHPStan-compatible sur CarbonImmutable).
        if (! $from instanceof CarbonImmutable) {
            throw new DomainException('ACCOUNTING_VAT_PERIOD_INVALID', 422, 'ACCOUNTING_VAT_PERIOD_INVALID');
        }

        return [$from, $from->endOfMonth()];
    }

    private function currency(Company $company): string
    {
        $settings = AccountingSettings::query()
            ->where('company_id', $company->id)
            ->first();

        // Les paramètres comptables sont provisionnés à la création de
        // l'entreprise (ProvisionAccountingSettings, ligne unique par company),
        // donc first() est non nul ici (même shape que GenerateDocumentPdf).
        return strtoupper($settings->currency ?? (string) ($company->currency ?? 'DZD'));
    }

    /**
     * Devise de référence du tenant (#9373) — même résolution que le posting :
     * paramétrage comptable, puis devise de l'entreprise, puis DZD.
     */
    private function referenceCurrency(string $companyId): string
    {
        $settingsCurrency = DB::table('accounting_settings')
            ->where('company_id', $companyId)
            ->value('currency');

        if (is_string($settingsCurrency) && $settingsCurrency !== '') {
            return strtoupper($settingsCurrency);
        }

        $companyCurrency = DB::table('companies')->where('id', $companyId)->value('currency');

        return is_string($companyCurrency) && $companyCurrency !== ''
            ? strtoupper($companyCurrency)
            : 'DZD';
    }

    /**
     * Documents de la période exprimés dans une autre devise que celle de la
     * déclaration — exclus de l'agrégation, signalés à l'utilisateur.
     */
    private function unconvertedDocuments(Company $company, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $reference = $this->referenceCurrency((string) $company->id);

        return (int) DB::table('accounting_documents')
            ->where('company_id', $company->id)
            ->whereNotNull('currency')
            ->whereRaw('UPPER(currency) <> ?', [$reference])
            ->whereNotIn('status', [DocumentStatus::Draft->value, DocumentStatus::Cancelled->value])
            ->whereDate('issue_date', '>=', $from->toDateString())
            ->whereDate('issue_date', '<=', $to->toDateString())
            ->count();
    }
}
