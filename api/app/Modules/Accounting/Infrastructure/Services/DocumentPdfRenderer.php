<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Contracts\PdfRendererInterface;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Support\I18nCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

/**
 * Rendu PDF des documents comptables multi-langues (issue #5224).
 *
 * Implémente le contrat posé par le socle data (#5221) : 6 types de document
 * (DocumentType) × 4 langues (fr/en/tr/ar RTL). Vue Blade unique paramétrée,
 * mentions légales issues de AccountingSettings (repli footer_mentions),
 * moteur dompdf existant (même pattern que AttendanceReportService::toPdf).
 */
final class DocumentPdfRenderer implements PdfRendererInterface
{
    public const SUPPORTED_LOCALES = ['fr', 'en', 'tr', 'ar'];

    public function render(AccountingDocument $document, string $locale): string
    {
        $previous = app()->getLocale();

        try {
            app()->setLocale($locale);

            $html = view('pdf.accounting-document', $this->buildViewData($document, $locale))->render();

            return Pdf::loadHTML($html)
                ->setPaper('a4')
                ->output();
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * Données de la vue — exposé pour les tests golden (montants HT/TVA/TTC)
     * sans dépendre du binaire PDF.
     *
     * @return array<string, mixed>
     */
    public function buildViewData(AccountingDocument $document, string $locale): array
    {
        /** @var Company|null $company */
        $company = Company::query()->find($document->company_id);

        $settings = AccountingSettings::query()
            ->where('company_id', $document->company_id)
            ->first();

        return [
            'document' => $document,
            'company' => $company,
            'contact' => $document->contact,
            'lines' => $this->lineRows($document->lines),
            'settings' => $settings,
            'locale' => $locale,
            'rtl' => I18nCatalog::isRtl($locale),
            'document_type_label' => __('accounting.document_type_'.$document->type),
            'status_label' => __('accounting.status_'.$document->status),
            'totals' => $this->totals($document),
            'legal_mentions' => $settings->legal_mentions ?? $document->footer_mentions,
        ];
    }

    /**
     * @param  Collection<int, AccountingDocumentLine>  $lines
     * @return array<int, array<string, int|float|string>>
     */
    private function lineRows(Collection $lines): array
    {
        return $lines
            ->map(fn (AccountingDocumentLine $line): array => [
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_price' => round((float) $line->unit_price, 2),
                'discount' => round((float) $line->discount, 2),
                'amount' => round(((float) $line->quantity * (float) $line->unit_price) - (float) $line->discount, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, int|float|null>
     */
    private function totals(AccountingDocument $document): array
    {
        $subtotal = round((float) $document->subtotal_ht, 2);
        $tax = round((float) $document->tax_amount, 2);
        $total = round((float) $document->total_ttc, 2);
        $paid = round((float) $document->paid_amount, 2);

        return [
            'subtotal_ht' => $subtotal,
            'tax_amount' => $tax,
            'tva_rate' => $document->tva_rate !== null ? round((float) $document->tva_rate, 2) : null,
            'total_ttc' => $total,
            'paid_amount' => $paid,
            'remaining' => round($total - $paid, 2),
        ];
    }
}
