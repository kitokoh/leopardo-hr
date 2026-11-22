<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduFeeCharge;
use App\Modules\EduManager\Domain\Models\EduFeePayment;
use App\Modules\EduManager\Domain\Models\EduFeeType;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * Reçu PDF d'un ENCAISSEMENT EN LOT (#9793).
 *
 * Le guichet encaisse une classe entière en une saisie, puis doit remettre une
 * pièce au payeur. Sans reçu, le caissier recopiait à la main la liste des
 * élèves et le total — et un parent ne pouvait rien produire en cas de
 * contestation.
 *
 * Même mécanique canonique que `RetailDocumentPdfRenderer` (#7813) et
 * `InvoicePdfRenderer` (#7763) : DomPDF + template Blade du module, aucun
 * import cross-module (garde #5584).
 *
 * Le lot est identifié par `batch_id` (colonne ajoutée par la migration
 * #9793) : c'est le seul regroupement fiable, `external_id` étant optionnel.
 */
class EduFeeBatchReceiptPdfRenderer
{
    /**
     * @return array{content: string, filename: string}
     */
    public function render(string $companyId, string $batchId): array
    {
        /** @var \Illuminate\Support\Collection<int, EduFeePayment> $payments */
        $payments = EduFeePayment::query()
            ->where('company_id', $companyId)
            ->where('batch_id', $batchId)
            ->with([
                'charge:id,student_id,fee_type_id,currency',
                'charge.student:id,student_number,display_name',
                'charge.feeType:id,code,label',
            ])
            ->orderBy('id')
            ->get();

        $lines = [];
        $total = 0.0;
        $currency = null;

        foreach ($payments as $payment) {
            $charge = $payment->charge;
            $student = $charge instanceof EduFeeCharge ? $charge->student : null;
            $feeType = $charge instanceof EduFeeCharge ? $charge->feeType : null;

            $amount = round((float) $payment->amount, 2);
            $total += $amount;
            $currency ??= (string) $payment->currency;

            $lines[] = [
                'student_number' => $student instanceof EduStudent ? $student->student_number : '—',
                'student_name' => $student instanceof EduStudent ? $student->display_name : '—',
                'fee_type' => $feeType instanceof EduFeeType ? $feeType->label : '—',
                'amount' => $this->money($amount, (string) $payment->currency),
            ];
        }

        $first = $payments->first();

        $pdf = Pdf::loadView('pdf.edu-fee-batch-receipt', [
            'company' => $this->resolveCompany($companyId),
            'batchReference' => $batchId,
            'paidAt' => $first instanceof EduFeePayment ? $first->paid_at->format('Y-m-d H:i') : '',
            'method' => $first instanceof EduFeePayment ? (string) $first->method : EduFeePayment::METHOD_CASH,
            'reference' => $first instanceof EduFeePayment ? $first->reference : null,
            'lines' => $lines,
            'total' => $this->money($total, (string) ($currency ?? '')),
        ]);

        $pdf->setPaper('A4', 'portrait');

        return [
            'content' => $pdf->output(),
            'filename' => sprintf('recu_lot_%s.pdf', $this->slug($batchId)),
        ];
    }

    /**
     * Nombre d'encaissements d'un lot — permet à l'appelant de répondre 404
     * avant de rendre un PDF vide.
     */
    public function countFor(string $companyId, string $batchId): int
    {
        return EduFeePayment::query()
            ->where('company_id', $companyId)
            ->where('batch_id', $batchId)
            ->count();
    }

    private function money(float $amount, string $currency): string
    {
        return trim(number_format($amount, 2, '.', ' ').' '.$currency);
    }

    /**
     * Nom de fichier sûr : le `batch_id` peut être une référence libre saisie
     * au guichet (« BORD-2026-09 »), donc jamais concaténée telle quelle dans
     * un en-tête `Content-Disposition`.
     */
    private function slug(string $value): string
    {
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', $value) ?? '';

        return trim($slug, '-') !== '' ? trim($slug, '-') : 'lot';
    }

    /**
     * Lecture QUALIFIÉE de `public.companies` (même garde que
     * `InvoicePdfRenderer` #7763) : le rendu peut être déclenché hors contexte
     * tenant strict où le search_path n'est pas garanti sur `public`.
     */
    private function resolveCompany(string $companyId): ?Company
    {
        $company = Company::query()
            ->from(DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies')
            ->find($companyId);

        return $company instanceof Company ? $company : null;
    }
}
